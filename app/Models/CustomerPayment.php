<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    use Auditable, BelongsToCompany, BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'currency_code',
        'exchange_rate',
        'bank_account_id',
        'company_id',
        'customer_id',
        'invoice_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** A document that hangs on another (a payment on its invoice...) belongs to that document's branch. */
    public function inheritedBranchId(): ?int
    {
        return Invoice::withoutGlobalScope('branch')->whereKey($this->invoice_id)->value('branch_id');
    }
}
