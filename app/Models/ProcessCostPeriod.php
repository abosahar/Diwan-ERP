<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessCostPeriod extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = ['company_id', 'branch_id', 'process_stage_id', 'date_from', 'date_to', 'status', 'units_started', 'units_completed', 'units_opening_wip', 'units_closing_wip', 'closing_material_pct', 'closing_conversion_pct', 'opening_ti', 'opening_mat', 'opening_conv', 'added_ti', 'added_mat', 'added_conv', 'closing_ti', 'closing_mat', 'closing_conv', 'eu_ti', 'cpu_ti', 'eu_mat', 'cpu_mat', 'eu_conv', 'cpu_conv', 'transferred_out', 'unit_cost', 'notes', 'created_by'];

    protected $casts = ['date_from' => 'date', 'date_to' => 'date', 'units_started' => 'decimal:4', 'units_completed' => 'decimal:4', 'units_opening_wip' => 'decimal:4', 'units_closing_wip' => 'decimal:4', 'closing_material_pct' => 'decimal:2', 'closing_conversion_pct' => 'decimal:2', 'transferred_out' => 'decimal:2', 'unit_cost' => 'decimal:6', 'opening_ti' => 'decimal:2', 'opening_mat' => 'decimal:2', 'opening_conv' => 'decimal:2', 'added_ti' => 'decimal:2', 'added_mat' => 'decimal:2', 'added_conv' => 'decimal:2', 'closing_ti' => 'decimal:2', 'closing_mat' => 'decimal:2', 'closing_conv' => 'decimal:2', 'eu_ti' => 'decimal:4', 'cpu_ti' => 'decimal:6', 'eu_mat' => 'decimal:4', 'cpu_mat' => 'decimal:6', 'eu_conv' => 'decimal:4', 'cpu_conv' => 'decimal:6'];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class, 'process_stage_id');
    }

    /** The branch of the warehouse the document works in. */
    public function inheritedBranchId(): ?int
    {
        return Warehouse::withoutGlobalScopes()->whereKey(ProcessStage::whereKey($this->process_stage_id)->value('warehouse_id'))->value('branch_id');
    }
}
