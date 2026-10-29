<?php

namespace App\Services\Sales;

use App\Models\CommissionPlan;
use App\Models\CommissionPosting;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\Payroll;
use App\Models\User;
use App\Services\HR\PayrollService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly sales commission per salesperson: net sales (invoices minus the month's
 * credit notes on their invoices) run through the salesperson's plan, falling back
 * to the flat rate on the user record. The result can be sent to payroll once.
 */
class CommissionCalculator
{
    public function __construct(protected TenantManager $tenantManager) {}

    public function forMonth(int $year, int $month): array
    {
        $companyId = $this->tenantManager->getTenantId();
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from)).' 23:59:59';

        $sales = DB::table('invoices')->where('company_id', $companyId)->whereNotNull('salesperson_id')
            ->whereBetween('invoice_date', [$from, $to])->groupBy('salesperson_id')
            ->selectRaw('salesperson_id, SUM(subtotal * COALESCE(exchange_rate, 1)) as net')->pluck('net', 'salesperson_id');

        $returns = DB::table('credit_notes as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->where('c.company_id', $companyId)->whereIn('c.status', ['approved', 'refunded'])->whereNotNull('i.salesperson_id')
            ->whereBetween('c.credit_note_date', [$from, $to])->groupBy('i.salesperson_id')
            ->selectRaw('i.salesperson_id, SUM(c.subtotal * COALESCE(c.exchange_rate, 1)) as net')->pluck('net', 'i.salesperson_id');

        $plans = CommissionPlan::where('is_active', true)->get()->keyBy('id');
        $posted = CommissionPosting::where('year', $year)->where('month', $month)->pluck('amount', 'user_id');
        $employees = Employee::whereNotNull('user_id')->pluck('id', 'user_id');

        $users = User::where('company_id', $companyId)->where('is_super_admin', false)
            ->where(fn ($q) => $q->whereNotNull('commission_plan_id')->orWhere('commission_rate', '>', 0)->orWhereIn('id', $sales->keys()))
            ->orderBy('name')->get(['id', 'name', 'commission_rate', 'commission_plan_id']);

        return $users->map(function (User $user) use ($sales, $returns, $plans, $posted, $employees) {
            $gross = round((float) ($sales[$user->id] ?? 0), 2);
            $back = round((float) ($returns[$user->id] ?? 0), 2);
            $net = round($gross - $back, 2);
            $plan = $user->commission_plan_id ? $plans->get($user->commission_plan_id) : null;

            if ($plan) {
                $commission = $plan->commissionOn($net);
                $bonus = $plan->bonusFor($net);
            } else {
                $commission = round(max($net, 0) * (float) $user->commission_rate / 100, 2);
                $bonus = 0.0;
            }

            return [
                'user_id' => $user->id, 'name' => $user->name, 'plan' => $plan?->name ?? ((float) $user->commission_rate > 0 ? 'نسبة المستخدم '.(float) $user->commission_rate.'%' : null),
                'sales' => $gross, 'returns' => $back, 'net' => $net, 'commission' => $commission, 'bonus' => $bonus, 'total' => round($commission + $bonus, 2),
                'employee_id' => $employees[$user->id] ?? null, 'posted' => isset($posted[$user->id]), 'posted_amount' => isset($posted[$user->id]) ? (float) $posted[$user->id] : null,
            ];
        })->filter(fn ($r) => $r['net'] != 0 || $r['total'] > 0 || $r['posted'])->values()->all();
    }

    /** Send the month's commission of one salesperson to payroll (as a «commission» adjustment). */
    public function postToPayroll(int $userId, int $year, int $month): CommissionPosting
    {
        $row = collect($this->forMonth($year, $month))->firstWhere('user_id', $userId);
        if (! $row || $row['total'] <= 0) {
            throw ValidationException::withMessages(['user' => 'لا توجد عمولة مستحقة لهذا الشهر.']);
        }
        if ($row['posted']) {
            throw ValidationException::withMessages(['user' => 'سبق ترحيل عمولة هذا الشهر إلى الرواتب.']);
        }
        if (! $row['employee_id']) {
            throw ValidationException::withMessages(['user' => 'المستخدم غير مرتبط بموظف؛ اربطه من بطاقة الموظف ليدخل في الرواتب.']);
        }
        $payroll = Payroll::where('year', $year)->where('month', $month)->first();
        if ($payroll && $payroll->status !== 'draft') {
            throw ValidationException::withMessages(['user' => 'مسير هذا الشهر معتمد؛ سجّل العمولة في الشهر التالي.']);
        }

        return DB::transaction(function () use ($row, $year, $month, $payroll) {
            $adjustment = EmployeeAdjustment::create([
                'employee_id' => $row['employee_id'], 'year' => $year, 'month' => $month, 'type' => 'commission', 'amount' => $row['total'],
                'description' => sprintf('عمولة مبيعات %02d/%d — صافي المبيعات %s', $month, $year, number_format($row['net'], 2, '.', '')), 'created_by' => auth()->id(),
            ]);
            if ($payroll) {
                app(PayrollService::class)->recalculate($payroll);
            }

            return CommissionPosting::create(['user_id' => $row['user_id'], 'year' => $year, 'month' => $month, 'amount' => $row['total'], 'employee_adjustment_id' => $adjustment->id]);
        });
    }
}
