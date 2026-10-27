<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractOrderMaterial extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'subcontract_order_id', 'product_id', 'planned_quantity', 'sent_quantity', 'consumed_quantity'];

    protected $casts = ['planned_quantity' => 'decimal:4', 'sent_quantity' => 'decimal:4', 'consumed_quantity' => 'decimal:4'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tenantParent(): ?Model
    {
        return $this->order;
    }
}
