<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'period_type', 'status', 'closing_entry_id', 'closed_at', 'closed_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime'];

    public const PERIOD_TYPES = [
        'monthly' => 'شهرية (12 فترة)',
        'quarterly' => 'ربع سنوية (4 فترات)',
        'semiannual' => 'نصف سنوية (فترتان)',
        'semimonthly' => 'نصف شهرية (24 فترة)',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('start_date');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
