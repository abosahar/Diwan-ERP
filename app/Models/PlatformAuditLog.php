<?php

namespace App\Models;

use App\Traits\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    use SerializesLocalDates;

    protected $fillable = ['user_id', 'company_id', 'action', 'details', 'ip'];

    protected $casts = ['details' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
