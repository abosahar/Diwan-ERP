<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrpRun extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'run_date', 'horizon_days', 'notes', 'created_by'];

    protected $casts = ['run_date' => 'date'];

    public function suggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class);
    }
}
