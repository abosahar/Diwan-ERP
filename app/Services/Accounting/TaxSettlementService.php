<?php

namespace App\Services\Accounting;

use App\Models\TaxSettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «تسوية الإقرار»: clears the period's output and input tax from the ledger into
 * one account (VAT payable to the authority). Settled periods cannot overlap.
 */
class TaxSettlementService
{
    public function __construct(protected AccountingReports $reports, protected AccountMap $map, protected PostingService $posting) {}

    /** The settlement covering any day of the range, if one exists. */
    public function overlapping(string $taxType, Carbon $from, Carbon $to): ?TaxSettlement
    {
        return TaxSettlement::query()->where('tax_type', $taxType)
            ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())
            ->first();
    }

    /** Ledger balances of the period that a settlement would clear. */
    public function preview(Carbon $from, Carbon $to): array
    {
        $vat = $this->reports->vatReturn($from, $to);

        return [
            'output' => $vat['ledger_output_vat'],
            'input' => $vat['ledger_input_vat'],
            'net' => round($vat['ledger_output_vat'] - $vat['ledger_input_vat'], 2),
            'matches' => $vat['ledger_matches'],
            'settlement' => $this->overlapping('vat', $from, $to)?->load('journalEntry:id,entry_number'),
        ];
    }

    public function settleVat(Carbon $from, Carbon $to): TaxSettlement
    {
        if ($existing = $this->overlapping('vat', $from, $to)) {
            throw ValidationException::withMessages(['start_date' => "الفترة (أو جزء منها) سبق تسويتها: من {$existing->start_date->toDateString()} إلى {$existing->end_date->toDateString()}."]);
        }

        $p = $this->preview($from, $to);
        if (abs($p['output']) < 0.005 && abs($p['input']) < 0.005) {
            throw ValidationException::withMessages(['start_date' => 'لا توجد ضريبة في دفتر الأستاذ لهذه الفترة لتسويتها.']);
        }

        return DB::transaction(function () use ($from, $to, $p) {
            $label = "تسوية إقرار ضريبة القيمة المضافة من {$from->toDateString()} إلى {$to->toDateString()}";
            $lines = [];
            if ($p['output'] > 0) {
                $lines[] = ['role' => 'vat_output', 'debit' => $p['output'], 'credit' => 0, 'description' => $label];
            } elseif ($p['output'] < 0) {
                $lines[] = ['role' => 'vat_output', 'debit' => 0, 'credit' => -$p['output'], 'description' => $label];
            }
            if ($p['input'] > 0) {
                $lines[] = ['role' => 'vat_input', 'debit' => 0, 'credit' => $p['input'], 'description' => $label];
            } elseif ($p['input'] < 0) {
                $lines[] = ['role' => 'vat_input', 'debit' => -$p['input'], 'credit' => 0, 'description' => $label];
            }
            // Net due to the authority is a credit; a refundable balance leaves the account in debit.
            $lines[] = ['role' => 'vat_settlement', 'debit' => $p['net'] < 0 ? -$p['net'] : 0, 'credit' => $p['net'] > 0 ? $p['net'] : 0, 'description' => $label];

            $settlement = TaxSettlement::create([
                'tax_type' => 'vat', 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(),
                'output_amount' => $p['output'], 'input_amount' => $p['input'], 'net_amount' => $p['net'], 'created_by' => auth()->id(),
            ]);

            $entry = $this->posting->post([
                'date' => $to->toDateString(), 'description' => $label, 'reference' => 'VAT-'.$to->format('Ymd'),
                'entry_type' => 'system', 'source' => $settlement, 'lines' => $lines,
            ]);
            $settlement->update(['journal_entry_id' => $entry->id]);

            return $settlement;
        });
    }

    /** Undo a settlement: reverse its entry and free the period. */
    public function cancel(TaxSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement) {
            if ($settlement->journalEntry) {
                $this->posting->reverse($settlement->journalEntry);
            }
            $settlement->delete();
        });
    }
}
