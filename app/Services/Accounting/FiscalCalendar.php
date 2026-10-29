<?php

namespace App\Services\Accounting;

use App\Models\CompanySetting;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fiscal years and monthly periods. Years are created on demand (calendar
 * years by default, or starting at the company's configured month).
 */
class FiscalCalendar
{
    /**
     * The period containing the date; creates the fiscal year when missing.
     */
    public function periodFor(CarbonInterface|string $date, int $companyId): FiscalPeriod
    {
        $date = Carbon::parse($date)->startOfDay();

        $period = FiscalPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            // Inclusive whole-day bounds (date columns may carry a 00:00:00 time on some drivers).
            ->where('start_date', '<=', $date->toDateString().' 23:59:59')
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($period) {
            return $period;
        }

        $this->createYear($this->yearStartFor($date, $companyId), $companyId);

        return FiscalPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            // Inclusive whole-day bounds (date columns may carry a 00:00:00 time on some drivers).
            ->where('start_date', '<=', $date->toDateString().' 23:59:59')
            ->where('end_date', '>=', $date->toDateString())
            ->firstOrFail();
    }

    /**
     * Throws when the date falls in a closed or locked period.
     */
    public function assertOpen(CarbonInterface|string $date, int $companyId): FiscalPeriod
    {
        $period = $this->periodFor($date, $companyId);

        if (! $period->isOpen()) {
            throw new AccountingException("الفترة المحاسبية ({$period->name}) مغلقة. لا يمكن الترحيل بتاريخ ".Carbon::parse($date)->toDateString().'.');
        }

        return $period;
    }

    public function createYear(CarbonInterface $start, int $companyId, ?string $type = null): FiscalYear
    {
        $type = array_key_exists((string) $type, FiscalYear::PERIOD_TYPES) ? $type : $this->defaultPeriodType($companyId);
        $start = Carbon::parse($start)->startOfMonth();
        $end = $start->copy()->addYear()->subDay();

        $existing = FiscalYear::withoutGlobalScopes()->where('company_id', $companyId)->whereDate('start_date', $start->toDateString())->first();
        if ($existing) {
            return $existing;
        }

        $year = FiscalYear::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => $start->month === 1 ? (string) $start->year : $start->year.'/'.$end->year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'period_type' => $type,
            'status' => 'open',
        ]);

        foreach ($this->periodRanges($start, $type, $year->name) as [$name, $from, $to]) {
            FiscalPeriod::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $year->id,
                'name' => $name,
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'status' => 'open',
            ]);
        }

        return $year;
    }

    /**
     * The periods of a year starting on $start: [name, from, to] each.
     *
     * @return array<int, array{0: string, 1: Carbon, 2: Carbon}>
     */
    public function periodRanges(CarbonInterface $start, string $type, string $yearName): array
    {
        $start = Carbon::parse($start)->startOfMonth();
        $ranges = [];

        if ($type === 'quarterly' || $type === 'semiannual') {
            $months = $type === 'quarterly' ? 3 : 6;
            $letter = $type === 'quarterly' ? 'Q' : 'H';
            for ($i = 0; $i < 12 / $months; $i++) {
                $from = $start->copy()->addMonths($i * $months);
                $ranges[] = ["{$yearName}-{$letter}".($i + 1), $from, $from->copy()->addMonths($months)->subDay()];
            }

            return $ranges;
        }

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            if ($type === 'semimonthly') {
                $ranges[] = [$month->format('Y-m').'-A', $month->copy(), $month->copy()->day(15)];
                $ranges[] = [$month->format('Y-m').'-B', $month->copy()->day(16), $month->copy()->endOfMonth()->startOfDay()];
            } else {
                $ranges[] = [$month->format('Y-m'), $month->copy(), $month->copy()->endOfMonth()->startOfDay()];
            }
        }

        return $ranges;
    }

    /** Type used when a year is created on demand by a posting (company setting, default monthly). */
    public function defaultPeriodType(int $companyId): string
    {
        $type = CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->where('key', 'fiscal_period_type')->value('value');

        return array_key_exists((string) $type, FiscalYear::PERIOD_TYPES) ? $type : 'monthly';
    }

    /**
     * First day of the fiscal year containing the date, honouring the
     * company's "fiscal_year_start_month" setting (default January).
     */
    public function yearStartFor(CarbonInterface $date, int $companyId): Carbon
    {
        $startMonth = (int) (CompanySetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'fiscal_year_start_month')
            ->value('value') ?: 1);

        $start = Carbon::create($date->year, $startMonth, 1);

        return $start->gt($date) ? $start->subYear() : $start;
    }
}
