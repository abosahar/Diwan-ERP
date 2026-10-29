<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalCalendar;
use App\Services\Accounting\YearEndClosing;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounting/FiscalYears/Index', [
            'years' => FiscalYear::with('periods')->orderByDesc('start_date')->get(),
            'periodTypes' => FiscalYear::PERIOD_TYPES,
        ]);
    }

    public function create(): Response
    {
        $start = now()->addYear()->startOfYear();

        return Inertia::render('Accounting/FiscalYears/Create', [
            'periodTypes' => FiscalYear::PERIOD_TYPES,
            'defaults' => [
                'name' => 'السنة المالية '.$start->year,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addYear()->subDay()->toDateString(),
                'period_type' => 'monthly',
            ],
        ]);
    }

    /** Live preview of the periods a year would get, and the existing year it would clash with. */
    public function preview(Request $request, FiscalCalendar $calendar)
    {
        [$start, $end, $type] = $this->bounds($request);

        $periods = collect($calendar->periodRanges($start, $type, $calendar->defaultName($start, $end), $end))
            ->map(fn ($p, $i) => ['n' => $i + 1, 'label' => $p[3], 'code' => $p[0], 'from' => $p[1]->toDateString(), 'to' => $p[2]->toDateString()])->values();
        $clash = $this->overlapping($start, $end);

        return response()->json([
            'periods' => $periods,
            'overlap' => $clash ? ['name' => $clash->name, 'from' => $clash->start_date->toDateString(), 'to' => $clash->end_date->toDateString()] : null,
            'months' => (int) $start->diffInMonths($end->copy()->addDay()),
        ]);
    }

    public function store(Request $request, FiscalCalendar $calendar)
    {
        [$start, $end, $type] = $this->bounds($request);
        $request->validate(['name' => 'nullable|string|max:100']);

        if ($this->overlapping($start, $end)) {
            return back()->withErrors(['start_date' => 'تتداخل هذه السنة المالية مع سنة مالية أخرى موجودة بالفعل لهذه المنشأة — لا يمكن أن تمتلك المنشأة سنتين ماليتين متداخلتين.']);
        }

        $calendar->createYear($start, app(TenantManager::class)->getTenantId(), $type, $end, $request->input('name') ?: null);

        return redirect()->route('accounting.fiscal-years.index')->with('success', 'تم إنشاء السنة المالية وفتراتها: '.FiscalYear::PERIOD_TYPES[$type]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} start (month start), end (month end), period type */
    protected function bounds(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'period_type' => ['nullable', Rule::in(array_keys(FiscalYear::PERIOD_TYPES))],
        ]);
        $start = Carbon::parse($validated['start_date'])->startOfMonth();
        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfMonth()->startOfDay() : $start->copy()->addYear()->subDay();
        if ($start->diffInMonths($end->copy()->addDay()) > 18) {
            throw ValidationException::withMessages(['end_date' => 'لا يمكن أن تزيد السنة المالية عن 18 شهرًا.']);
        }

        return [$start, $end, $validated['period_type'] ?? 'monthly'];
    }

    protected function overlapping(Carbon $start, Carbon $end): ?FiscalYear
    {
        return FiscalYear::whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->first();
    }

    /**
     * Open / close / lock a period (whatever the year's period type).
     */
    public function updatePeriod(Request $request, FiscalPeriod $period)
    {
        $validated = $request->validate(['status' => 'required|in:open,closed,locked']);

        if ($period->status === 'locked') {
            return back()->with('error', 'الفترة مقفلة نهائياً ولا يمكن تغيير حالتها.');
        }
        if ($validated['status'] === 'open' && $period->fiscalYear->isClosed()) {
            return back()->with('error', 'السنة المالية مغلقة. أعد فتح السنة أولاً.');
        }

        $period->update(['status' => $validated['status']]);

        return back()->with('success', 'تم تحديث حالة الفترة');
    }

    public function close(FiscalYear $year, YearEndClosing $closing)
    {
        $entry = $closing->close($year);

        return back()->with('success', $entry
            ? "تم إقفال السنة المالية وترحيل صافي النتيجة للأرباح المحتجزة بالقيد {$entry->entry_number}"
            : 'تم إقفال السنة المالية');
    }

    public function reopen(FiscalYear $year, YearEndClosing $closing)
    {
        $closing->reopen($year);

        return back()->with('success', 'تم إعادة فتح السنة المالية وعكس قيد الإقفال');
    }
}
