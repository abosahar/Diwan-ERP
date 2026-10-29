<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;

/**
 * Applies the company's active promotions to the lines of a sales document being
 * created by hand. Per line the best matching promotion wins (no stacking); a line
 * the user already discounted by hand is left alone.
 */
class PromotionEngine
{
    /**
     * @param  array  $lines  [product_id?, quantity, unit_price, discount?, ...]
     * @param  string  $document  invoice | quotation | order
     * @return array{0: array, 1: array<int, string>} lines with discounts, names of the promotions applied
     */
    public function apply(array $lines, ?int $customerId, string $document, ?string $date = null): array
    {
        $date ??= now()->toDateString();
        $promotions = Promotion::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('priority')->get()
            ->filter(fn (Promotion $p) => in_array($document, $p->documents ?? [], true));
        if ($promotions->isEmpty()) {
            return [$lines, []];
        }

        $groupId = $customerId ? Customer::whereKey($customerId)->value('customer_group_id') : null;
        $categories = Product::whereIn('id', collect($lines)->pluck('product_id')->filter())->pluck('category_id', 'id');
        $applied = [];

        foreach ($lines as $i => $line) {
            if (empty($line['product_id']) || (float) ($line['discount'] ?? 0) > 0) {
                continue;
            }
            $qty = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            $best = null;
            $bestDiscount = 0.0;

            foreach ($promotions as $promo) {
                if ($promo->customer_group_id && (int) $promo->customer_group_id !== (int) $groupId) {
                    continue;
                }
                if ($promo->product_id && (int) $promo->product_id !== (int) $line['product_id']) {
                    continue;
                }
                if ($promo->category_id && (int) $promo->category_id !== (int) ($categories[$line['product_id']] ?? 0)) {
                    continue;
                }
                $discount = $this->discount($promo, $qty, $price);
                if ($discount > $bestDiscount + 0.004) {
                    $best = $promo;
                    $bestDiscount = $discount;
                }
            }

            if ($best) {
                $lines[$i]['discount'] = round(min($bestDiscount, $qty * $price), 2);
                $applied[$best->id] = $best->name;
            }
        }

        return [$lines, array_values($applied)];
    }

    protected function discount(Promotion $promo, float $qty, float $price): float
    {
        $gross = $qty * $price;

        if ($promo->type === 'bxgy') {
            $buy = (float) $promo->buy_qty;
            $get = (float) $promo->get_qty;
            if ($buy <= 0 || $get <= 0) {
                return 0.0;
            }

            return floor($qty / ($buy + $get)) * $get * $price;
        }

        $value = (float) $promo->value;
        if (! empty($promo->tiers)) {
            $value = 0.0;
            foreach (collect($promo->tiers)->sortBy('min_qty') as $tier) {
                if ($qty >= (float) $tier['min_qty']) {
                    $value = (float) $tier['value'];
                }
            }
        }

        return $promo->type === 'percent' ? $gross * $value / 100 : $qty * $value;
    }
}
