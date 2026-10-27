<?php

namespace App\Services\Manufacturing;

use App\Models\ProcessCostPeriod;
use App\Models\ProcessStage;
use App\Models\WorkCenter;
use App\Services\Accounting\PostingService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Process costing (continuous production), weighted-average method.
 *
 * Each stage collects three cost elements per period: transferred-in (from the
 * previous stage), materials and conversion (labour + overhead). Equivalent
 * units turn them into a cost per unit; completed units leave the stage at
 * that cost and the units still in process keep their share. The last stage
 * hands its units to finished-goods stock (Dr inventory / Cr WIP).
 */
class ProcessCostingService
{
    public function __construct(protected StockService $stock, protected PostingService $posting) {}

    public function openPeriod(ProcessStage $stage, string $from, string $to): ProcessCostPeriod
    {
        if (ProcessCostPeriod::where('process_stage_id', $stage->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['stage' => 'للمرحلة فترة مفتوحة بالفعل. أغلقها أولاً.']);
        }
        $previous = ProcessCostPeriod::where('process_stage_id', $stage->id)->where('status', 'closed')->orderByDesc('date_to')->first();

        return ProcessCostPeriod::create([
            'process_stage_id' => $stage->id, 'date_from' => $from, 'date_to' => $to, 'status' => 'open', 'created_by' => auth()->id(),
            'units_opening_wip' => $previous?->units_closing_wip ?? 0,
            'opening_ti' => $previous?->closing_ti ?? 0, 'opening_mat' => $previous?->closing_mat ?? 0, 'opening_conv' => $previous?->closing_conv ?? 0,
        ]);
    }

    /** @param array<int, array{product_id:int, quantity:float}> $lines */
    public function issueMaterials(ProcessCostPeriod $period, array $lines): float
    {
        $this->assertOpen($period);
        $stage = $period->stage;
        $date = Carbon::parse($period->date_to);

        return DB::transaction(function () use ($period, $stage, $lines, $date) {
            $cost = 0.0;
            foreach ($lines as $l) {
                $cost += $this->stock->issue([
                    'product_id' => $l['product_id'], 'warehouse_id' => $stage->warehouse_id, 'quantity' => $l['quantity'],
                    'type' => 'process_out', 'date' => $date, 'source' => $period, 'reference' => $stage->code,
                ]);
            }
            $cost = round($cost, 2);
            if ($cost > 0) {
                $this->posting->post(['date' => $date, 'description' => "صرف مواد لمرحلة {$stage->name}", 'source' => $period, 'lines' => [['role' => 'wip', 'debit' => $cost], ['role' => 'inventory', 'credit' => $cost]]]);
            }
            $period->update(['added_mat' => (float) $period->added_mat + $cost]);

            return $cost;
        });
    }

    /** Labour and machine time at a work centre's rates. */
    public function addConversion(ProcessCostPeriod $period, int $workCenterId, float $minutes): float
    {
        $this->assertOpen($period);
        $wc = WorkCenter::findOrFail($workCenterId);
        $hours = round($minutes, 2) / 60;
        $labour = round($hours * (float) $wc->labour_rate_per_hour, 2);
        $overhead = round($hours * (float) $wc->overhead_rate_per_hour, 2);
        if ($labour + $overhead <= 0) {
            throw ValidationException::withMessages(['work_center_id' => 'لمركز العمل تكلفة ساعة صفر. حدّد معدل الأجور والمصروفات.']);
        }

        return DB::transaction(function () use ($period, $wc, $labour, $overhead) {
            $lines = [['role' => 'wip', 'debit' => $labour + $overhead]];
            if ($labour > 0) {
                $lines[] = ['role' => 'applied_labour', 'credit' => $labour];
            }
            if ($overhead > 0) {
                $lines[] = ['role' => 'applied_overhead', 'credit' => $overhead];
            }
            $this->posting->post(['date' => Carbon::parse($period->date_to), 'description' => "تكلفة تحويل ({$wc->name}) لمرحلة {$period->stage->name}", 'source' => $period, 'lines' => $lines]);
            $period->update(['added_conv' => (float) $period->added_conv + $labour + $overhead]);

            return $labour + $overhead;
        });
    }

    /**
     * Close the period: equivalent units, cost per unit, cost of what was
     * completed and of what stays in process.
     *
     * @param  array{units_started:float, units_completed:float, units_closing_wip:float, closing_material_pct:float, closing_conversion_pct:float}  $in
     */
    public function close(ProcessCostPeriod $period, array $in): ProcessCostPeriod
    {
        $this->assertOpen($period);
        $stage = $period->stage;
        $completed = round((float) $in['units_completed'], 4);
        $closing = round((float) $in['units_closing_wip'], 4);
        $matPct = max(0, min(100, (float) $in['closing_material_pct'])) / 100;
        $convPct = max(0, min(100, (float) $in['closing_conversion_pct'])) / 100;
        if ($completed <= 0 && $closing <= 0) {
            throw ValidationException::withMessages(['units_completed' => 'أدخل الوحدات المكتملة أو التي بقيت تحت التشغيل.']);
        }
        $final = $stage->isFinal();
        if (! $final) {
            $next = ProcessStage::where('previous_stage_id', $stage->id)->first();
            if (! ProcessCostPeriod::where('process_stage_id', $next->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['stage' => "افتح فترة المرحلة التالية «{$next->name}» أولاً لتستلم الوحدات المحوّلة."]);
            }
        }

        return DB::transaction(function () use ($period, $stage, $in, $completed, $closing, $matPct, $convPct, $final) {
            $eu = ['ti' => $completed + $closing, 'mat' => $completed + $closing * $matPct, 'conv' => $completed + $closing * $convPct];
            $update = [
                'units_started' => $in['units_started'], 'units_completed' => $completed, 'units_closing_wip' => $closing,
                'closing_material_pct' => $matPct * 100, 'closing_conversion_pct' => $convPct * 100, 'status' => 'closed',
            ];
            $out = 0.0;
            foreach ($eu as $el => $units) {
                $total = round((float) $period->{"opening_{$el}"} + (float) $period->{"added_{$el}"}, 2);
                $cpu = $units > 0 ? $total / $units : 0.0;
                $done = round($completed * $cpu, 2);
                $update["eu_{$el}"] = $units;
                $update["cpu_{$el}"] = round($cpu, 6);
                $update["closing_{$el}"] = round($total - $done, 2); // what is left stays in process, so nothing is lost to rounding
                $out += $done;
            }
            $out = round($out, 2);
            $update['transferred_out'] = $out;
            $update['unit_cost'] = $completed > 0 ? round($out / $completed, 6) : 0;
            $period->update($update);

            if ($final) {
                if ($completed > 0) {
                    $this->stock->receive([
                        'product_id' => $stage->product_id, 'warehouse_id' => $stage->warehouse_id, 'quantity' => $completed, 'unit_cost' => $out / $completed,
                        'type' => 'process_in', 'date' => Carbon::parse($period->date_to), 'source' => $period, 'reference' => $stage->code,
                    ]);
                    $this->posting->post(['date' => Carbon::parse($period->date_to), 'description' => "إنتاج تام من مرحلة {$stage->name}", 'source' => $period, 'lines' => [['role' => 'inventory', 'debit' => $out], ['role' => 'wip', 'credit' => $out]]]);
                }
            } else {
                $nextStage = ProcessStage::where('previous_stage_id', $stage->id)->first();
                $nextPeriod = ProcessCostPeriod::where('process_stage_id', $nextStage->id)->where('status', 'open')->first();
                $nextPeriod->update(['added_ti' => (float) $nextPeriod->added_ti + $out]);
            }

            return $period->refresh();
        });
    }

    protected function assertOpen(ProcessCostPeriod $period): void
    {
        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['period' => 'الفترة مغلقة.']);
        }
    }
}
