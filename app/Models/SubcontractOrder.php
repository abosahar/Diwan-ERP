<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractOrder extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = ['company_id', 'branch_id', 'order_number', 'supplier_id', 'product_id', 'bom_id', 'source_warehouse_id', 'vendor_warehouse_id', 'output_warehouse_id', 'quantity', 'received_quantity', 'service_price', 'order_date', 'expected_date', 'status', 'notes', 'created_by'];

    protected $casts = ['quantity' => 'decimal:4', 'received_quantity' => 'decimal:4', 'service_price' => 'decimal:4', 'order_date' => 'date', 'expected_date' => 'date'];

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function vendorWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'vendor_warehouse_id');
    }

    public function outputWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SubcontractOrderMaterial::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(SubcontractReceipt::class);
    }

    /** The branch of the warehouse the document works in. */
    public function inheritedBranchId(): ?int
    {
        return Warehouse::withoutGlobalScopes()->whereKey($this->source_warehouse_id)->value('branch_id');
    }
}
