<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderMaterial extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'production_order_id', 'product_id', 'planned_quantity', 'issued_quantity', 'returned_quantity', 'cost'];

    protected $casts = ['planned_quantity' => 'decimal:4', 'issued_quantity' => 'decimal:4', 'returned_quantity' => 'decimal:4', 'cost' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tenantParent(): ?Model
    {
        return $this->order;
    }

    public function netIssued(): float
    {
        return (float) $this->issued_quantity - (float) $this->returned_quantity;
    }
}
