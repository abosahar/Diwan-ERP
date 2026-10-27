<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcPlan extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'product_id', 'applies_to', 'checks', 'is_active'];

    protected $casts = ['checks' => 'array', 'is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
