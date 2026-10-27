<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'product_id', 'name', 'version', 'is_default', 'output_quantity', 'scrap_percent', 'routing_id', 'notes', 'is_active'];

    protected $casts = ['output_quantity' => 'decimal:4', 'is_active' => 'boolean', 'is_default' => 'boolean', 'scrap_percent' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }
}
