<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Expense;
use App\Models\Revenue;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\AccountMap;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Edit / delete actions added to lists that had none. */
class ListActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company));
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function balance(string $role): float
    {
        return round((float) Account::find(app(AccountMap::class)->id($role))->balance, 2);
    }

    public function test_expense_and_revenue_vouchers_are_edited_on_the_same_number(): void
    {
        $base = ['title' => 'إيجار', 'category' => 'عام', 'payment_method' => 'cash', 'account_id' => app(AccountMap::class)->id('rent_expense'), 'expense_date' => now()->toDateString()];
        $this->post(route('finance.expenses.store'), $base + ['amount' => 100])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->get(route('finance.expenses.edit', $expense->id))->assertOk()->assertInertia(fn ($p) => $p->component('Finance/Expenses/Form')->where('expense.id', $expense->id));
        $this->put(route('finance.expenses.update', $expense->id), $base + ['amount' => 250])->assertSessionHasNoErrors();
        $this->assertSame($expense->expense_number, $expense->fresh()->expense_number);
        $this->assertEquals(250, $expense->fresh()->total_amount);
        $this->assertSame(250.0, $this->balance('rent_expense'));

        $rev = ['title' => 'عمولة', 'category' => 'عام', 'payment_method' => 'cash', 'revenue_date' => now()->toDateString()];
        $this->post(route('finance.revenues.store'), $rev + ['amount' => 80])->assertSessionHasNoErrors();
        $revenue = Revenue::firstOrFail();
        $this->put(route('finance.revenues.update', $revenue->id), $rev + ['amount' => 60])->assertSessionHasNoErrors();
        $this->assertEquals(60, $revenue->fresh()->total_amount);
        $this->assertSame(1, Revenue::count());
    }

    public function test_categories_banks_and_customer_groups(): void
    {
        $category = Category::create(['name_ar' => 'قديم', 'is_active' => true]);
        $this->put(route('inventory.categories.update', $category->id), ['name_ar' => 'جديد'])->assertSessionHasNoErrors();
        $this->assertSame('جديد', $category->fresh()->name_ar);

        $this->post(route('finance.banks.store'), ['name' => 'بنك', 'type' => 'bank', 'currency' => 'SAR', 'opening_balance' => 0])->assertSessionHasNoErrors();
        $bank = BankAccount::firstOrFail();
        $this->get(route('finance.banks.edit', $bank->id))->assertOk();
        $this->put(route('finance.banks.update', $bank->id), ['name' => 'بنك الراجحي', 'iban' => 'SA00'])->assertSessionHasNoErrors();
        $this->assertSame('بنك الراجحي', $bank->fresh()->name);

        $group = CustomerGroup::create(['code' => 'G1', 'name' => 'مجموعة']);
        $this->delete(route('sales.customer-groups.destroy', $group->id))->assertRedirect();
        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);

        foreach (['hr.employees.index', 'sales.orders.index', 'purchases.orders.index', 'accounting.journals.index', 'sales.quotations.index', 'finance.expenses.index', 'finance.banks.index', 'inventory.categories.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }
}
