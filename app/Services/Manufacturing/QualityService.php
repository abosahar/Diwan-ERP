<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionReceipt;
use App\Models\QcInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quality control: inspections against a plan's checks. An inspection of a
 * production receipt decides what reaches stock: accepted units are received
 * at their share of the order's cost, scrapped units are written off, and
 * units sent back for rework stay in work in progress.
 */
class QualityService
{
    public function __construct(protected ProductionService $production) {}

    /**
     * Pass / fail of every check of a plan for the values measured.
     *
     * @param  array<int, array{name:string, type:string, min?:?float, max?:?float}>  $checks
     * @param  array<int, mixed>  $values  keyed like $checks
     * @return array<int, array{name:string, value:mixed, passed:bool}>
     */
    public function evaluate(array $checks, array $values): array
    {
        $out = [];
        foreach ($checks as $i => $check) {
            $value = $values[$i] ?? null;
            if (($check['type'] ?? 'passfail') === 'numeric') {
                $n = is_numeric($value) ? (float) $value : null;
                $passed = $n !== null
                    && (! isset($check['min']) || $check['min'] === '' || $n >= (float) $check['min'])
                    && (! isset($check['max']) || $check['max'] === '' || $n <= (float) $check['max']);
            } else {
                $passed = in_array($value, [true, 1, '1', 'pass', 'yes'], true);
            }
            $out[] = ['name' => $check['name'] ?? '', 'value' => $value, 'passed' => $passed];
        }

        return $out;
    }

    /** @param array{accepted:float, rejected:float, rework?:float, disposition?:string, results?:array, notes?:?string} $data */
    public function complete(QcInspection $inspection, array $data): QcInspection
    {
        if ($inspection->result !== 'pending') {
            throw ValidationException::withMessages(['inspection' => 'اكتمل هذا الفحص من قبل.']);
        }
        $accepted = round((float) $data['accepted'], 4);
        $rejected = round((float) $data['rejected'], 4);
        $rework = round((float) ($data['rework'] ?? 0), 4);
        if (abs($accepted + $rejected + $rework - (float) $inspection->inspected_quantity) > 0.0001) {
            throw ValidationException::withMessages(['accepted' => 'المقبول + المرفوض + إعادة التشغيل يجب أن يساوي الكمية المفحوصة.']);
        }
        $disposition = $data['disposition'] ?? ($rejected > 0 ? 'scrap' : 'accept');

        return DB::transaction(function () use ($inspection, $data, $accepted, $rejected, $rework, $disposition) {
            $inspection->update([
                'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected, 'rework_quantity' => $rework,
                'result' => $accepted <= 0 ? 'fail' : (($rejected + $rework) > 0 ? 'partial' : 'pass'),
                'disposition' => $disposition, 'results' => $data['results'] ?? null, 'notes' => $data['notes'] ?? null, 'inspector_id' => auth()->id(),
            ]);

            $source = $inspection->source;
            if ($source instanceof ProductionReceipt && $source->status === 'pending_qc') {
                $scrap = $disposition === 'scrap' ? $rejected : 0.0;
                if ($accepted <= 0 && $scrap <= 0) {
                    $source->update(['status' => 'rejected', 'quantity' => 0]);
                } else {
                    $this->production->postReceipt($source, $accepted, (float) $source->scrap_quantity + $scrap, $scrap > 0 || (bool) $source->scrap_abnormal);
                }
            }

            return $inspection->refresh();
        });
    }
}
