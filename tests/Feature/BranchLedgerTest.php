<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\AccountingSetupService;
use App\Services\ActiveBranch;
use App\Services\Sales\InvoiceService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every financial record belongs to the branch chosen in the top bar; reports
 * show one branch or the consolidated figures.
 */
class BranchLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $owner;

    protected Branch $riyadh;

    protected Branch $cairo;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->company = Company::factory()->create(['currency' => 'SAR', 'country_code' => 'SA', 'tax_number' => '310122393500003']);
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
        $this->owner = $this->userWithRole($this->company);
        $this->actingAs($this->owner);
        $this->riyadh = Branch::create(['name_ar' => 'الرياض', 'code' => 'RUH', 'is_main' => true, 'is_active' => true]);
        $this->cairo = Branch::create(['name_ar' => 'القاهرة', 'code' => 'CAI', 'is_active' => true]);
        Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function expense(float $amount): void
    {
        $this->post(route('finance.expenses.store'), ['title' => 'إيجار', 'category' => 'إيجار', 'amount' => $amount, 'expense_date' => '2026-06-10', 'payment_method' => 'cash']);
    }

    protected function branchOfLastEntry(): ?int
    {
        return JournalEntry::withoutGlobalScope('branch')->latest('id')->first()->branch_id;
    }

    protected function totals(?array $ids): array
    {
        app(ActiveBranch::class)->flush();

        return app(AccountingReports::class)->trialBalance(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'))['totals'];
    }

    public function test_entries_and_expenses_follow_the_branch_chosen_in_the_top_bar(): void
    {
        $this->post(route('context.branch'), ['branch_id' => $this->cairo->id])->assertSessionHasNoErrors();
        $this->expense(500);
        $this->assertSame($this->cairo->id, Expense::withoutGlobalScope('branch')->latest('id')->first()->branch_id);
        $this->assertSame($this->cairo->id, $this->branchOfLastEntry());

        $this->post(route('context.branch'), ['branch_id' => $this->riyadh->id]);
        $this->expense(200);
        $this->assertSame($this->riyadh->id, $this->branchOfLastEntry());
    }

    public function test_a_payment_follows_the_branch_of_its_invoice(): void
    {
        $invoice = app(InvoiceService::class)->create(['customer_id' => $this->customer->id, 'invoice_date' => '2026-06-15', 'branch_id' => $this->cairo->id, 'payment_method' => 'credit'],
            [['item_name' => 'خدمة', 'quantity' => 1, 'unit_price' => 100]]);
        $this->assertSame($this->cairo->id, $this->branchOfLastEntry());

        // Working in Riyadh, the Cairo invoice is hidden: switch there to collect it.
        $this->post(route('context.branch'), ['branch_id' => $this->cairo->id]);
        $this->post(route('sales.payments.store'), ['customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'amount' => 50, 'payment_date' => '2026-06-15', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame($this->cairo->id, $this->branchOfLastEntry());
        $this->assertSame($this->cairo->id, CustomerPayment::withoutGlobalScope('branch')->latest('id')->first()->branch_id);
    }

    public function test_the_trial_balance_shows_one_branch_or_all_of_them(): void
    {
        $this->post(route('context.branch'), ['branch_id' => $this->riyadh->id]);
        $this->expense(300);
        $this->post(route('context.branch'), ['branch_id' => $this->cairo->id]);
        $this->expense(700);

        $cairo = $this->totals(null);
        $this->post(route('context.branch'), ['branch_id' => $this->riyadh->id]);
        $riyadh = $this->totals(null);
        $this->post(route('context.branch'), ['branch_id' => 'all']);
        $all = $this->totals(null);

        $this->assertSame(700.0, (float) $cairo['debit']);
        $this->assertSame(300.0, (float) $riyadh['debit']);
        $this->assertSame(1000.0, (float) $all['debit']);
    }

    public function test_nothing_is_posted_while_all_branches_are_shown(): void
    {
        $this->post(route('context.branch'), ['branch_id' => 'all']);
        $this->expense(100);

        $this->assertSame(0, Expense::withoutGlobalScope('branch')->count());
        $this->assertSame(0, JournalEntry::withoutGlobalScope('branch')->count());
    }
}
