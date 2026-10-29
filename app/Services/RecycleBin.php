<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Deleted quotations and sales / purchase orders stay in the recycle bin for
 * RETENTION_DAYS and can be restored; after that they are erased for good. Issued
 * invoices are never deletable (tax law) — they are reversed with credit notes.
 */
class RecycleBin
{
    public const RETENTION_DAYS = 90;

    /** type => [model, Arabic label, number column, permission to delete] */
    public const TYPES = [
        'quotation' => [Quotation::class, 'عرض سعر', 'quotation_number', 'sales.quotations.delete'],
        'sales_order' => [SalesOrder::class, 'أمر بيع', 'order_number', 'sales.orders.cancel'],
        'purchase_order' => [PurchaseOrder::class, 'أمر شراء', 'order_number', 'purchases.orders.cancel'],
    ];

    /** Move a document to the bin, or explain why it cannot be deleted. */
    public function trash(Model $document): void
    {
        $blocker = match (true) {
            $document instanceof Quotation => $document->status === 'converted' || $document->converted_invoice_id ? 'عرض السعر تحوّل إلى فاتورة ولا يمكن حذفه.' : null,
            $document instanceof SalesOrder => $document->items()->where(fn ($q) => $q->where('delivered_quantity', '>', 0)->orWhere('invoiced_quantity', '>', 0))->exists() ? 'لا يمكن حذف أمر بيع تم التسليم أو الفوترة عليه.' : null,
            $document instanceof PurchaseOrder => ($document->items()->where('invoiced_quantity', '>', 0)->exists() || GoodsReceipt::where('purchase_order_id', $document->id)->where('status', '!=', 'cancelled')->exists()) ? 'لا يمكن حذف أمر شراء تم الاستلام أو الفوترة عليه.' : null,
            default => 'نوع مستند غير مدعوم.',
        };

        if ($blocker) {
            throw ValidationException::withMessages(['document' => $blocker]);
        }

        $document->delete();
    }

    /** Erase documents deleted more than the retention period ago (current tenant). */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach (self::TYPES as [$model]) {
            $model::onlyTrashed()->where('deleted_at', '<', now()->subDays(self::RETENTION_DAYS))->each(function ($document) use (&$count) {
                $document->items()->delete();
                $document->forceDelete();
                $count++;
            });
        }

        return $count;
    }
}
