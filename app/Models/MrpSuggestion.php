<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpSuggestion extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'mrp_run_id', 'product_id', 'type', 'quantity', 'gross_demand', 'on_hand', 'supply', 'need_date', 'status', 'converted_type', 'converted_id'];

    protected $casts = ['quantity' => 'decimal:4', 'gross_demand' => 'decimal:4', 'on_hand' => 'decimal:4', 'supply' => 'decimal:4', 'need_date' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'mrp_run_id');
    }
}
