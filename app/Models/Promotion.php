<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A promotional offer applied automatically to sales lines. */
class Promotion extends Model
{
    use Auditable, BelongsToCompany;

    public const TYPES = ['percent' => 'خصم نسبة %', 'fixed' => 'خصم مبلغ ثابت للوحدة', 'bxgy' => 'اشترِ X واحصل على Y مجانًا'];

    public const DOCUMENTS = ['invoice' => 'فواتير المبيعات', 'quotation' => 'عروض الأسعار', 'order' => 'أوامر البيع'];

    protected $fillable = ['company_id', 'name', 'type', 'value', 'tiers', 'buy_qty', 'get_qty', 'product_id', 'category_id', 'customer_group_id', 'documents', 'valid_from', 'valid_to', 'priority', 'is_active'];

    protected $casts = ['tiers' => 'array', 'documents' => 'array', 'is_active' => 'boolean', 'value' => 'float', 'buy_qty' => 'float', 'get_qty' => 'float', 'valid_from' => 'date', 'valid_to' => 'date'];

    public function getLabelAttribute(): string
    {
        return $this->name;
    }
}
