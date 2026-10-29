<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A nationality an employee can have (company's own list, seeded from config/nationalities.php). */
class Nationality extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name_ar', 'name_en', 'is_active', 'sort'];

    protected $casts = ['is_active' => 'boolean'];

    public function getLabelAttribute(): string
    {
        return $this->name_ar;
    }
}
