<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Services\Manufacturing\ManufacturingReports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, ManufacturingReports $reports): Response
    {
        $f = $request->validate(['report' => 'nullable|in:summary,wip,materials,utilization', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $report = $f['report'] ?? 'summary';
        $from = Carbon::parse($f['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($f['to'] ?? now());

        return Inertia::render('Manufacturing/Reports/Index', [
            'report' => $report,
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'data' => match ($report) {
                'wip' => $reports->wip(),
                'materials' => $reports->materialUsage($from, $to),
                'utilization' => $reports->utilization($from, $to),
                default => $reports->summary($from, $to),
            },
        ]);
    }
}
