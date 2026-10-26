<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use Auditable, BelongsToCompany, BranchScoped;

    protected $fillable = [
        'branch_id', 'company_id', 'supplier_id', 'purchase_invoice_id', 'warehouse_id', 'return_number', 'return_date', 'reason', 'settlement', 'bank_account_id', 'subtotal', 'tax_amount', 'total', 'cost_value', 'status', 'created_by'];

    protected $casts = ['return_date' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'cost_value' => 'decimal:2'];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** A document that hangs on another (a payment on its invoice...) belongs to that document's branch. */
    public function inheritedBranchId(): ?int
    {
        return PurchaseInvoice::withoutGlobalScope('branch')->whereKey($this->purchase_invoice_id)->value('branch_id');
    }
}
