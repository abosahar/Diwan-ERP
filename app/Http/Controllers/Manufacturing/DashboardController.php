<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\ProductionReceipt;
use App\Models\QcInspection;
use App\Models\SubcontractOrder;
use App\Services\Manufacturing\CapacityPlanner;
use App\Services\Manufacturing\ManufacturingReports;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(ManufacturingReports $reports, CapacityPlanner $planner): Response
    {
        $today = now()->toDateString();
        $wip = $reports->wip();

        return Inertia::render('Manufacturing/Dashboard', [
            'counts' => ProductionOrder::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'late' => ProductionOrder::with('product:id,name_ar')->whereIn('status', ['released', 'in_progress'])->whereNotNull('planned_end')->where('planned_end', '<', $today)
                ->orderBy('planned_end')->limit(8)->get(['id', 'order_number', 'product_id', 'planned_end', 'status', 'quantity', 'received_quantity']),
            'wipTotal' => round(collect($wip)->sum('wip'), 2),
            'wipOrders' => array_slice($wip, 0, 8),
            'pendingQc' => QcInspection::where('result', 'pending')->count(),
            'openSubcontracts' => SubcontractOrder::whereIn('status', ['sent', 'partially_received'])->count(),
            'capacity' => collect($planner->load(4))->map(fn ($r) => ['name' => $r['name'], 'weeks' => $r['weeks']])->all(),
            'recent' => ProductionOrder::with('product:id,name_ar')->latest('id')->limit(8)->get(['id', 'order_number', 'product_id', 'status', 'quantity', 'received_quantity']),
            'monthOutput' => (float) ProductionReceipt::where('status', 'posted')->whereBetween('date', [now()->startOfMonth()->toDateString(), $today])->sum('quantity'),
        ]);
    }
}
