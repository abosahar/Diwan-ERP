<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractReceipt extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'subcontract_order_id', 'date', 'quantity', 'material_cost', 'service_cost', 'total_cost', 'notes', 'created_by'];

    protected $casts = ['date' => 'date', 'quantity' => 'decimal:4', 'material_cost' => 'decimal:2', 'service_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id');
    }
}
