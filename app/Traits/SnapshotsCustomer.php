<?php

namespace App\Traits;

use App\Models\Customer;
use Illuminate\Support\Arr;

/**
 * A tax document keeps the customer's registration data as it was when the document
 * was created (name, tax number, national address...), so editing the customer later
 * never changes old invoices, their PDFs or their e-invoice XML. Read it through
 * `$document->buyer`; documents made before this existed fall back to the live customer.
 */
trait SnapshotsCustomer
{
    public const CUSTOMER_SNAPSHOT_FIELDS = [
        'code', 'customer_type', 'name', 'company_name', 'contact_name', 'email', 'phone', 'mobile', 'tax_number', 'commercial_registration',
        'address', 'building_number', 'street_name', 'district', 'city', 'province', 'postal_code', 'additional_details', 'country',
    ];

    public static function bootSnapshotsCustomer(): void
    {
        static::creating(function ($document) {
            if (empty($document->customer_snapshot) && $document->customer_id && ($customer = Customer::withoutGlobalScopes()->find($document->customer_id))) {
                $document->customer_snapshot = Arr::only($customer->getAttributes(), self::CUSTOMER_SNAPSHOT_FIELDS);
            }
        });
    }

    public function initializeSnapshotsCustomer(): void
    {
        $this->mergeCasts(['customer_snapshot' => 'array']);
    }

    /** The customer as of the document date (an unsaved copy), or the live customer for older documents. */
    public function getBuyerAttribute(): ?Customer
    {
        $live = $this->customer;
        if (! $live || empty($this->customer_snapshot)) {
            return $live;
        }

        $copy = $live->replicate();
        $copy->forceFill($this->customer_snapshot);
        $copy->id = $live->id;

        return $copy;
    }
}
