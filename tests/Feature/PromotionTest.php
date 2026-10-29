<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Sales\PromotionEngine;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company));
        Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
        $this->product = Product::create(['name_ar' => 'صنف', 'sku' => 'P1', 'selling_price' => 100, 'purchase_price' => 40, 'track_inventory' => false, 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function promo(array $attributes): Promotion
    {
        return Promotion::create($attributes + ['name' => 'عرض', 'type' => 'percent', 'value' => 10, 'documents' => ['invoice', 'quotation', 'order'], 'is_active' => true]);
    }

    protected function line(float $qty, float $discount = 0): array
    {
        return [['product_id' => $this->product->id, 'item_name' => 'صنف', 'quantity' => $qty, 'unit_price' => 100, 'discount' => $discount]];
    }

    public function test_tiered_percent_picks_the_highest_tier_reached(): void
    {
        $this->promo(['tiers' => [['min_qty' => 1, 'value' => 5], ['min_qty' => 10, 'value' => 15]]]);
        $engine = app(PromotionEngine::class);

        $this->assertEquals(5, $engine->apply($this->line(2), $this->customer->id, 'invoice')[0][0]['discount'] / 200 * 100);
        [$lines, $names] = $engine->apply($this->line(10), $this->customer->id, 'invoice');
        $this->assertEquals(150, $lines[0]['discount']);
        $this->assertSame(['عرض'], $names);
    }

    public function test_buy_x_get_y_and_fixed_per_unit(): void
    {
        $this->promo(['type' => 'bxgy', 'buy_qty' => 2, 'get_qty' => 1, 'value' => 0]);
        $this->assertEquals(200, app(PromotionEngine::class)->apply($this->line(7), $this->customer->id, 'order')[0][0]['discount']); // 2 free of 7

        Promotion::query()->delete();
        $this->promo(['type' => 'fixed', 'value' => 3]);
        $this->assertEquals(12, app(PromotionEngine::class)->apply($this->line(4), $this->customer->id, 'quotation')[0][0]['discount']);
    }

    public function test_scope_group_dates_and_manual_discounts_are_respected(): void
    {
        $group = CustomerGroup::create(['code' => 'VIP', 'name' => 'مميزون']);
        $this->promo(['customer_group_id' => $group->id]);
        $engine = app(PromotionEngine::class);

        $this->assertEquals(0, $engine->apply($this->line(1), $this->customer->id, 'invoice')[0][0]['discount'] ?? 0, 'other customers get nothing');
        $this->customer->update(['customer_group_id' => $group->id]);
        $this->assertEquals(10, $engine->apply($this->line(1), $this->customer->id, 'invoice')[0][0]['discount']);
        $this->assertEquals(7, $engine->apply($this->line(1, 7), $this->customer->id, 'invoice')[0][0]['discount'], 'manual discount wins');
        Promotion::first()->update(['valid_to' => now()->subDay()->toDateString()]);
        $this->assertEquals(0, $engine->apply($this->line(1), $this->customer->id, 'invoice')[0][0]['discount'] ?? 0, 'expired');
    }

    public function test_an_invoice_created_by_hand_gets_the_offer_and_the_page_works(): void
    {
        $this->promo(['value' => 20]);
        $this->post(route('sales.invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'payment_method' => 'cash', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->product->id, 'item_name' => 'صنف', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 15]],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $this->assertEquals(40, $invoice->items()->first()->discount);
        $this->assertEquals(184, $invoice->total); // (200-40) * 1.15

        $this->get(route('sales.promotions.index'))->assertOk();
        $this->post(route('sales.promotions.store'), ['name' => 'جديد', 'type' => 'percent', 'value' => 5, 'documents' => ['invoice']])->assertSessionHasNoErrors();
        $this->post(route('sales.promotions.store'), ['name' => 'خطأ', 'type' => 'percent', 'value' => 150, 'documents' => ['invoice']])->assertSessionHasErrors('value');
    }
}
