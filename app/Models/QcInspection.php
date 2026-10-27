<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QcInspection extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = ['company_id', 'branch_id', 'inspection_number', 'qc_plan_id', 'product_id', 'source_type', 'source_id', 'inspection_date', 'inspected_quantity', 'accepted_quantity', 'rejected_quantity', 'rework_quantity', 'result', 'disposition', 'results', 'inspector_id', 'notes'];

    protected $casts = ['inspection_date' => 'date', 'inspected_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4', 'rework_quantity' => 'decimal:4', 'results' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QcPlan::class, 'qc_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The branch of the warehouse the document works in. */
    public function inheritedBranchId(): ?int
    {
        return null;
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
