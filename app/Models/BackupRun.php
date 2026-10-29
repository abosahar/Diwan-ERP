<?php

namespace App\Models;

use App\Traits\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;

/** Platform backup / verification history (not tenant data). */
class BackupRun extends Model
{
    use SerializesLocalDates;

    protected $fillable = ['type', 'file', 'size', 'status', 'details', 'error', 'duration_ms'];

    protected $casts = ['details' => 'array'];
}
