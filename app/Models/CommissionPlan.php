<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** How a salesperson's monthly commission is worked out: flat %, marginal tiers, and a target bonus. */
class CommissionPlan extends Model
{
    use Auditable, BelongsToCompany;

    public const TYPES = ['flat' => 'نسبة ثابتة من صافي المبيعات', 'tiered' => 'شرائح (نسبة أعلى كلما زادت المبيعات)'];

    protected $fillable = ['company_id', 'name', 'type', 'rate', 'tiers', 'target_amount', 'bonus_amount', 'is_active'];

    protected $casts = ['tiers' => 'array', 'rate' => 'float', 'target_amount' => 'float', 'bonus_amount' => 'float', 'is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'commission_plan_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->name;
    }

    /** Commission of $netSales under this plan (without the target bonus). */
    public function commissionOn(float $netSales): float
    {
        if ($netSales <= 0) {
            return 0.0;
        }
        if ($this->type === 'flat' || empty($this->tiers)) {
            return round($netSales * $this->rate / 100, 2);
        }

        $tiers = collect($this->tiers)->sortBy('from')->values();
        $total = 0.0;
        foreach ($tiers as $i => $tier) {
            $from = (float) $tier['from'];
            $to = isset($tiers[$i + 1]) ? (float) $tiers[$i + 1]['from'] : INF;
            if ($netSales > $from) {
                $total += (min($netSales, $to) - $from) * (float) $tier['rate'] / 100;
            }
        }

        return round($total, 2);
    }

    public function bonusFor(float $netSales): float
    {
        return $this->target_amount && $this->bonus_amount && $netSales >= $this->target_amount ? round($this->bonus_amount, 2) : 0.0;
    }
}
