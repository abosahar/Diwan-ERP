<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'bom_id', 'component_id', 'quantity', 'scrap_percent'];

    protected $casts = ['quantity' => 'decimal:4', 'scrap_percent' => 'decimal:2'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function tenantParent(): ?Model
    {
        return $this->bom;
    }
}
