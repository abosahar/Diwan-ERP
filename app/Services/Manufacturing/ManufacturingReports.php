<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionOrder;
use App\Models\ProductionTimeLog;
use App\Models\WorkCenter;
use App\Services\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManufacturingReports
{
    /** Planned against actual cost of one production order. */
    public function costSheet(ProductionOrder $order): array
    {
        $order->loadMissing(['product:id,name_ar,sku', 'materials.product:id,name_ar,sku,average_cost', 'operations.workCenter']);
        $materials = $order->materials->map(fn ($m) => [
            'product' => $m->product?->name_ar, 'planned' => (float) $m->planned_quantity, 'issued' => $m->netIssued(),
            'cost' => (float) $m->cost, 'planned_cost' => round((float) $m->planned_quantity * (float) ($m->product?->average_cost ?? 0), 2),
        ])->values()->all();
        $operations = $order->operations->map(function ($o) {
            $rate = (float) ($o->workCenter?->hourlyCost() ?? 0);

            return [
                'name' => $o->name, 'work_center' => $o->workCenter?->name, 'planned_minutes' => (float) $o->planned_minutes, 'actual_minutes' => (float) $o->actual_minutes,
                'cost' => round((float) $o->labour_cost + (float) $o->overhead_cost, 2), 'planned_cost' => round((float) $o->planned_minutes / 60 * $rate, 2),
            ];
        })->values()->all();

        $plannedMaterial = array_sum(array_column($materials, 'planned_cost'));
        $plannedConversion = array_sum(array_column($operations, 'planned_cost'));
        $received = (float) $order->received_quantity;

        return [
            'materials' => $materials, 'operations' => $operations,
            'planned' => ['material' => round($plannedMaterial, 2), 'conversion' => round($plannedConversion, 2), 'total' => round($plannedMaterial + $plannedConversion, 2)],
            'actual' => ['material' => (float) $order->material_cost, 'labour' => (float) $order->labour_cost, 'overhead' => (float) $order->overhead_cost, 'total' => $order->accumulatedCost()],
            'unit_cost' => $received > 0 ? round((float) $order->transferred_cost / $received, 4) : null,
            'variance' => (float) $order->variance,
        ];
    }

    /** Open orders and what each still holds in work in progress. */
    public function wip(): array
    {
        return ProductionOrder::with('product:id,name_ar')->whereIn('status', ['released', 'in_progress', 'completed'])->get()
            ->map(fn ($o) => [
                'id' => $o->id, 'number' => $o->order_number, 'product' => $o->product?->name_ar, 'status' => $o->status,
                'wip' => $o->wipBalance(), 'material' => (float) $o->material_cost, 'labour' => (float) $o->labour_cost, 'overhead' => (float) $o->overhead_cost,
                'age_days' => $o->actual_start ? (int) $o->actual_start->diffInDays(now()) : 0,
            ])->filter(fn ($r) => $r['wip'] != 0.0)->sortByDesc('wip')->values()->all();
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        $orders = ProductionOrder::with('product:id,name_ar')->whereIn('status', ['completed', 'closed'])->whereBetween('actual_end', [$from->toDateString(), $to->toDateString()])->get();
        $byProduct = $orders->groupBy('product_id')->map(fn ($g) => [
            'product' => $g->first()->product?->name_ar, 'orders' => $g->count(), 'quantity' => round($g->sum('received_quantity'), 4),
            'scrap' => round($g->sum('scrap_quantity'), 4), 'cost' => round($g->sum('transferred_cost'), 2), 'variance' => round($g->sum('variance'), 2),
        ])->values()->all();

        return ['orders' => $orders->count(), 'by_product' => $byProduct, 'totals' => [
            'quantity' => round($orders->sum('received_quantity'), 4), 'scrap' => round($orders->sum('scrap_quantity'), 4),
            'cost' => round($orders->sum('transferred_cost'), 2), 'variance' => round($orders->sum('variance'), 2),
        ]];
    }

    /** Planned against consumed quantities by material, over closed orders. */
    public function materialUsage(Carbon $from, Carbon $to): array
    {
        return DB::table('production_order_materials as m')->join('production_orders as o', 'o.id', '=', 'm.production_order_id')->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('o.company_id', app(TenantManager::class)->getTenantId())->whereBetween('o.planned_start', [$from->toDateString(), $to->toDateString()])->where('o.status', '!=', 'cancelled')
            ->groupBy('m.product_id', 'p.name_ar')->selectRaw('m.product_id, p.name_ar as product, SUM(m.planned_quantity) as planned, SUM(m.issued_quantity - m.returned_quantity) as used, SUM(m.cost) as cost')
            ->get()->map(fn ($r) => ['product' => $r->product, 'planned' => (float) $r->planned, 'used' => (float) $r->used, 'cost' => round((float) $r->cost, 2), 'variance_qty' => round((float) $r->used - (float) $r->planned, 4)])->all();
    }

    /** Hours worked against capacity per work centre. */
    public function utilization(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->diffInWeekdays($to) + 1);

        return WorkCenter::where('is_active', true)->orderBy('code')->get()->map(function ($wc) use ($from, $to, $days) {
            $minutes = (float) ProductionTimeLog::where('work_center_id', $wc->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('minutes');
            $capacity = $wc->dailyMinutes() * $days;

            return ['code' => $wc->code, 'name' => $wc->name, 'minutes' => $minutes, 'capacity_minutes' => round($capacity, 2), 'pct' => $capacity > 0 ? round($minutes / $capacity * 100, 1) : 0];
        })->all();
    }
}
