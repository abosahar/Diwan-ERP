<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOperation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'production_order_id', 'seq', 'name', 'work_center_id', 'planned_minutes', 'actual_minutes', 'completed_quantity', 'status', 'planned_date', 'labour_cost', 'overhead_cost'];

    protected $casts = ['planned_minutes' => 'decimal:2', 'actual_minutes' => 'decimal:2', 'completed_quantity' => 'decimal:4', 'planned_date' => 'date', 'labour_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function tenantParent(): ?Model
    {
        return $this->order;
    }
}
