<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\CommissionCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Monthly commission statement per salesperson, and sending it to payroll. */
class CommissionController extends Controller
{
    public function index(Request $request, CommissionCalculator $calculator): Response
    {
        $f = $request->validate(['year' => 'nullable|integer|min:2000|max:2100', 'month' => 'nullable|integer|min:1|max:12']);
        $year = (int) ($f['year'] ?? now()->year);
        $month = (int) ($f['month'] ?? now()->month);

        return Inertia::render('Sales/Commissions/Index', [
            'rows' => $calculator->forMonth($year, $month),
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function post(Request $request, CommissionCalculator $calculator)
    {
        $d = $request->validate(['user_id' => 'required|integer', 'year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);
        $calculator->postToPayroll((int) $d['user_id'], (int) $d['year'], (int) $d['month']);

        return back()->with('success', 'تم ترحيل العمولة إلى مسير الرواتب');
    }
}
