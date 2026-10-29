<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FiscalCalendar;
use App\Services\Sales\InvoiceService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Document lists show only the fiscal year chosen in the top bar. */
class FiscalYearListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $company = Company::factory()->create(['currency' => 'SAR', 'country_code' => 'SA', 'tax_number' => '310122393500003']);
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company));
        Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
        app(FiscalCalendar::class)->createYear(Carbon::parse('2025-01-01'), $company->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function invoice(string $date): void
    {
        $customer = Customer::first() ?? Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);
        app(InvoiceService::class)->create(['customer_id' => $customer->id, 'invoice_date' => $date, 'payment_method' => 'credit'], [['item_name' => 'خدمة', 'quantity' => 1, 'unit_price' => 100]]);
    }

    public function test_invoices_follow_the_fiscal_year_chosen_in_the_top_bar(): void
    {
        $this->invoice('2025-03-10');
        $this->invoice('2026-02-01');
        $this->invoice('2026-05-01');

        // Today (2026-06-15) is in 2026: only its two invoices show, and the cards agree.
        $this->get(route('sales.invoices.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->has('invoices.data', 2)->where('stats.total_count', 2));

        $old = FiscalYear::whereDate('start_date', '2025-01-01')->firstOrFail();
        $this->post(route('context.fiscal-year'), ['fiscal_year_id' => $old->id])->assertSessionHasNoErrors();
        $this->get(route('sales.invoices.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->has('invoices.data', 1)->where('stats.total_count', 1));

        // An explicit date filter replaces the year.
        $this->get(route('sales.invoices.index', ['date_from' => '2025-01-01']))->assertOk()
            ->assertInertia(fn ($p) => $p->has('invoices.data', 3));
    }
}
