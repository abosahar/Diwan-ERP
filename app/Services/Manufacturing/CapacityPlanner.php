<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionOrderOperation;
use App\Models\WorkCenter;
use Illuminate\Support\Carbon;

/** Load of every work centre against its capacity, week by week. */
class CapacityPlanner
{
    public const WORKING_DAYS = 5;

    /** @return array<int, array{id:int, code:string, name:string, weekly_capacity:float, weeks: array<int, array{start:string, load:float, pct:float}>}> */
    public function load(int $weeks = 8, ?Carbon $from = null): array
    {
        $from = ($from ?? Carbon::today())->copy()->startOfWeek(Carbon::SATURDAY);
        $to = $from->copy()->addWeeks($weeks);

        $ops = ProductionOrderOperation::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['released', 'in_progress']))
            ->where('status', '!=', 'done')->whereNotNull('planned_date')
            ->whereBetween('planned_date', [$from->toDateString(), $to->toDateString()])
            ->get(['work_center_id', 'planned_minutes', 'actual_minutes', 'planned_date']);

        $rows = [];
        foreach (WorkCenter::where('is_active', true)->orderBy('code')->get() as $wc) {
            $weekly = round($wc->dailyMinutes() * self::WORKING_DAYS, 2);
            $buckets = [];
            for ($w = 0; $w < $weeks; $w++) {
                $start = $from->copy()->addWeeks($w);
                $load = $ops->where('work_center_id', $wc->id)->filter(fn ($o) => $o->planned_date->betweenIncluded($start, $start->copy()->addDays(6)))
                    ->sum(fn ($o) => max(0, (float) $o->planned_minutes - (float) $o->actual_minutes));
                $buckets[] = ['start' => $start->toDateString(), 'load' => round($load, 2), 'pct' => $weekly > 0 ? round($load / $weekly * 100, 1) : 0];
            }
            $rows[] = ['id' => $wc->id, 'code' => $wc->code, 'name' => $wc->name, 'weekly_capacity' => $weekly, 'weeks' => $buckets];
        }

        return $rows;
    }
}
