<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessStage extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'sequence', 'product_id', 'previous_stage_id', 'warehouse_id', 'work_center_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class, 'previous_stage_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ProcessCostPeriod::class);
    }

    /** The last stage turns the output into finished goods. */
    public function isFinal(): bool
    {
        return ! static::where('previous_stage_id', $this->id)->exists();
    }
}
