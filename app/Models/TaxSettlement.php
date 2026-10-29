<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tax return period that was settled: output and input tax cleared into the amount due to the authority. */
class TaxSettlement extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'tax_type', 'start_date', 'end_date', 'output_amount', 'input_amount', 'net_amount', 'journal_entry_id', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'output_amount' => 'decimal:2', 'input_amount' => 'decimal:2', 'net_amount' => 'decimal:2'];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
