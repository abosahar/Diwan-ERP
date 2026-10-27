<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = ['company_id', 'branch_id', 'order_number', 'product_id', 'bom_id', 'routing_id', 'warehouse_id', 'output_warehouse_id', 'quantity', 'received_quantity', 'scrap_quantity', 'planned_start', 'planned_end', 'actual_start', 'actual_end', 'status', 'priority', 'sales_order_id', 'material_cost', 'labour_cost', 'overhead_cost', 'transferred_cost', 'variance', 'notes', 'created_by'];

    protected $casts = ['planned_start' => 'date', 'planned_end' => 'date', 'actual_start' => 'date', 'actual_end' => 'date', 'quantity' => 'decimal:4', 'received_quantity' => 'decimal:4', 'scrap_quantity' => 'decimal:4', 'material_cost' => 'decimal:2', 'labour_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2', 'transferred_cost' => 'decimal:2', 'variance' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function outputWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(ProductionTimeLog::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProductionReceipt::class);
    }

    /** The branch of the warehouse the document works in. */
    public function inheritedBranchId(): ?int
    {
        return Warehouse::withoutGlobalScopes()->whereKey($this->warehouse_id)->value('branch_id');
    }

    public const STATUSES = ['draft', 'released', 'in_progress', 'completed', 'closed', 'cancelled'];

    /** Total cost accumulated in work in progress so far. */
    public function accumulatedCost(): float
    {
        return round((float) $this->material_cost + (float) $this->labour_cost + (float) $this->overhead_cost, 2);
    }

    /** Cost still sitting in WIP (not yet transferred to finished goods). */
    public function wipBalance(): float
    {
        return round($this->accumulatedCost() - (float) $this->transferred_cost - (float) $this->variance, 2);
    }

    public function remainingQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->received_quantity, 4));
    }
}
