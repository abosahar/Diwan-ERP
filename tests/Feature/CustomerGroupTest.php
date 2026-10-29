<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
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

    public function test_journal_lines_take_an_auxiliary_account(): void
    {
        $group = CustomerGroup::create(['code' => 'CG1', 'name' => 'جملة', 'account_id' => $this->wholesale->id]);
        $customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active', 'customer_group_id' => $group->id]);
        $cash = Account::find(app(AccountMap::class)->id('default_cash'));
        $box = BankAccount::create(['name' => 'الصندوق الرئيسي', 'type' => 'cash', 'currency' => 'SAR', 'opening_balance' => 0, 'current_balance' => 0, 'account_id' => $cash->id, 'is_active' => true]);
        $other = BankAccount::create(['name' => 'بنك آخر', 'type' => 'bank', 'currency' => 'SAR', 'opening_balance' => 0, 'current_balance' => 0, 'account_id' => Account::find(app(AccountMap::class)->id('default_bank'))->id, 'is_active' => true]);

        // The create page offers the group account as a customer control account and the cash account as a bank one.
        $props = $this->get(route('accounting.journals.create'))->assertOk()->viewData('page')['props'];
        $this->assertSame('customer', $props['partyAccounts'][(string) $this->wholesale->id]);
        $this->assertSame('bank', $props['partyAccounts'][(string) $cash->id]);

        $lines = fn (array $debit, array $credit) => ['date' => '2026-06-10', 'description' => 'قيد مساعد', 'save_as' => 'posted', 'lines' => [
            ['account_id' => $this->wholesale->id, 'debit' => 50, 'credit' => 0] + $debit,
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 50] + $credit,
        ]];

        // A group-account line needs its customer; a box of another account is refused.
        $this->post(route('accounting.journals.store'), $lines([], []))->assertSessionHasErrors('lines.0.party_id');
        $this->post(route('accounting.journals.store'), $lines(['party_type' => 'customer', 'party_id' => $customer->id], ['party_type' => 'bank', 'party_id' => $other->id]))->assertSessionHasErrors('lines.1.party_id');

        $this->post(route('accounting.journals.store'), $lines(['party_type' => 'customer', 'party_id' => $customer->id], ['party_type' => 'bank', 'party_id' => $box->id]))->assertSessionHasNoErrors();
        $this->assertSame($box->id, (int) JournalEntryLine::where('account_id', $cash->id)->where('party_type', 'bank')->value('party_id'));
    }

    public function test_an_invoice_keeps_the_customer_data_it_was_issued_with(): void
    {
        $customer = Customer::create(['name' => 'شركة الأفق', 'customer_type' => 'commercial', 'status' => 'active', 'tax_number' => '300000000000003', 'building_number' => '4021', 'street_name' => 'الرئيسي', 'district' => 'الصحافة', 'city' => 'الرياض', 'postal_code' => '13315']);
        $this->invoiceFor($customer);
        $invoice = Invoice::firstOrFail();
        $this->assertSame('شركة الأفق', $invoice->customer_snapshot['name']);

        // Editing the customer later does not change the invoice's buyer.
        $customer->update(['name' => 'اسم جديد', 'tax_number' => '399999999999993', 'city' => 'جدة']);
        $buyer = $invoice->fresh()->buyer;
        $this->assertSame('شركة الأفق', $buyer->name);
        $this->assertSame('300000000000003', $buyer->tax_number);
        $this->assertSame('الرياض', $buyer->city);
        $this->assertSame($customer->id, $buyer->id);

        // Documents without a snapshot (made before this existed) read the live customer.
        $invoice->forceFill(['customer_snapshot' => null])->saveQuietly();
        $this->assertSame('اسم جديد', $invoice->fresh()->buyer->name);
    }
}
