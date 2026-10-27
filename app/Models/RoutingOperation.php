<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'routing_id', 'seq', 'name', 'work_center_id', 'setup_minutes', 'run_minutes'];

    protected $casts = ['setup_minutes' => 'decimal:2', 'run_minutes' => 'decimal:4'];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function tenantParent(): ?Model
    {
        return $this->routing;
    }
}
