<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A feature or improvement a user asks for; colleagues vote, the administrators set its status. */
class FeatureRequest extends Model
{
    use Auditable, BelongsToCompany;

    public const STATUSES = [
        'new' => 'جديد',
        'reviewing' => 'قيد الدراسة',
        'planned' => 'مخطط له',
        'in_progress' => 'قيد التنفيذ',
        'done' => 'تم التنفيذ',
        'declined' => 'مرفوض',
    ];

    public const KINDS = ['feature' => 'ميزة جديدة', 'improvement' => 'تحسين على ميزة قائمة'];

    protected $fillable = ['company_id', 'user_id', 'kind', 'area', 'title', 'description', 'status', 'admin_note', 'released_in', 'votes_count'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feature_request_votes')->withTimestamps();
    }
}
