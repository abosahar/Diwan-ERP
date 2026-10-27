<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTimeLog extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'production_order_id', 'operation_id', 'work_center_id', 'employee_id', 'date', 'minutes', 'quantity', 'labour_cost', 'overhead_cost', 'notes', 'created_by'];

    protected $casts = ['date' => 'date', 'minutes' => 'decimal:2', 'quantity' => 'decimal:4', 'labour_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
