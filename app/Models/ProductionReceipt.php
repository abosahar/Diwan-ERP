<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReceipt extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = ['company_id', 'branch_id', 'production_order_id', 'date', 'quantity', 'scrap_quantity', 'scrap_abnormal', 'unit_cost', 'total_cost', 'status', 'batch_number', 'notes', 'created_by'];

    protected $casts = ['date' => 'date', 'quantity' => 'decimal:4', 'scrap_quantity' => 'decimal:4', 'scrap_abnormal' => 'boolean', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** The branch of the warehouse the document works in. */
    public function inheritedBranchId(): ?int
    {
        return ProductionOrder::withoutGlobalScope('branch')->whereKey($this->production_order_id)->value('branch_id');
    }
}
