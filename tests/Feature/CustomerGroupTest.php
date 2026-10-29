<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\JournalEntryLine;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\AccountMap;
use App\Services\Sales\InvoiceService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** A customer group can post its customers' receivables to its own ledger account. */
class CustomerGroupTest extends TestCase
{
    use RefreshDatabase;

    protected Account $wholesale;

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
        $general = Account::find(app(AccountMap::class)->id('receivables'));
        $this->wholesale = Account::create(['code' => '112101', 'name_ar' => 'عملاء الجملة', 'type' => 'asset', 'nature' => 'debit', 'parent_id' => $general->id, 'is_parent' => false, 'is_active' => true, 'balance' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function invoiceFor(Customer $customer): void
    {
        app(InvoiceService::class)->create(['customer_id' => $customer->id, 'invoice_date' => '2026-06-15', 'payment_method' => 'credit'], [['item_name' => 'خدمة', 'quantity' => 1, 'unit_price' => 100]]);
    }

    public function test_group_pages_and_json_create(): void
    {
        $this->post(route('sales.customer-groups.store'), ['code' => 'CG001', 'name' => 'عملاء الجملة', 'account_id' => $this->wholesale->id])->assertSessionHasNoErrors();
        $this->post(route('sales.customer-groups.store'), ['code' => 'CG001', 'name' => 'مكرر'])->assertSessionHasErrors('code');
        $this->post(route('sales.customer-groups.store'), ['code' => 'CG002', 'name' => 'ليس أصلاً', 'account_id' => Account::where('type', 'expense')->where('is_parent', false)->value('id')])->assertSessionHasErrors('account_id');

        $this->get(route('sales.customer-groups.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Sales/CustomerGroups/Index')->has('groups', 1));
        $this->postJson(route('sales.customer-groups.store'), ['code' => 'CG003', 'name' => 'تجزئة'])->assertCreated()->assertJsonPath('record.label', 'تجزئة');
    }

    public function test_customers_of_a_group_post_to_its_account_and_aging_still_adds_up(): void
    {
        $group = CustomerGroup::create(['code' => 'CG1', 'name' => 'جملة', 'account_id' => $this->wholesale->id]);
        $inGroup = Customer::create(['name' => 'داخل المجموعة', 'customer_type' => 'individual', 'status' => 'active', 'customer_group_id' => $group->id]);
        $plain = Customer::create(['name' => 'بدون مجموعة', 'customer_type' => 'individual', 'status' => 'active']);

        $this->invoiceFor($inGroup);
        $this->invoiceFor($plain);

        $general = app(AccountMap::class)->id('receivables');
        $this->assertSame($this->wholesale->id, (int) JournalEntryLine::where('party_id', $inGroup->id)->where('party_type', 'customer')->value('account_id'));
        $this->assertSame($general, (int) JournalEntryLine::where('party_id', $plain->id)->where('party_type', 'customer')->value('account_id'));

        // Receipt from the grouped customer clears its group account.
        $this->post(route('sales.payments.store'), ['customer_id' => $inGroup->id, 'amount' => 115, 'payment_date' => '2026-06-15', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.0, (float) JournalEntryLine::where('account_id', $this->wholesale->id)->sum('debit') - (float) JournalEntryLine::where('account_id', $this->wholesale->id)->sum('credit'), 0.01);

        // Aging adds up the general and the group accounts: only the plain customer still owes.
        $aging = app(AccountingReports::class)->aging('receivables', Carbon::parse('2026-06-30'));
        $this->assertEqualsWithDelta(115.0, collect($aging['rows'] ?? $aging)->sum(fn ($r) => (float) ($r['total'] ?? 0)), 0.01);
    }

    public function test_the_account_cannot_change_once_customers_have_postings(): void
    {
        $group = CustomerGroup::create(['code' => 'CG1', 'name' => 'جملة', 'account_id' => $this->wholesale->id]);
        $this->invoiceFor(Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active', 'customer_group_id' => $group->id]));

        $this->put(route('sales.customer-groups.update', $group->id), ['code' => 'CG1', 'name' => 'جملة', 'account_id' => ''])->assertSessionHasErrors('account_id');
        $this->put(route('sales.customer-groups.update', $group->id), ['code' => 'CG1', 'name' => 'جملة 2', 'account_id' => $this->wholesale->id])->assertSessionHasNoErrors();
    }
}
