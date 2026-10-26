<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCost extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = [
        'branch_id', 'company_id', 'purchase_invoice_id', 'landed_number', 'date', 'description', 'amount', 'tax_amount', 'paid_from', 'supplier_id', 'bank_account_id', 'allocation', 'created_by'];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'allocation' => 'array'];

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** A document that hangs on another (a payment on its invoice...) belongs to that document's branch. */
    public function inheritedBranchId(): ?int
    {
        return PurchaseInvoice::withoutGlobalScope('branch')->whereKey($this->purchase_invoice_id)->value('branch_id');
    }
}
