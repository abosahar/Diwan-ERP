<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenter extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'type', 'equipment_id', 'cost_center_id', 'capacity_hours_per_day', 'efficiency', 'labour_rate_per_hour', 'overhead_rate_per_hour', 'is_active', 'notes'];

    protected $casts = ['capacity_hours_per_day' => 'decimal:2', 'efficiency' => 'decimal:2', 'labour_rate_per_hour' => 'decimal:4', 'overhead_rate_per_hour' => 'decimal:4', 'is_active' => 'boolean'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** Productive minutes per working day (capacity x efficiency). */
    public function dailyMinutes(): float
    {
        return round((float) $this->capacity_hours_per_day * 60 * (float) $this->efficiency / 100, 2);
    }

    /** Cost of one hour: labour + overhead. */
    public function hourlyCost(): float
    {
        return (float) $this->labour_rate_per_hour + (float) $this->overhead_rate_per_hour;
    }
}
