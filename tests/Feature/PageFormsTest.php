<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The main "add / edit" screens are full pages, not pop-ups.
 */
class PageFormsTest extends TestCase
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
        Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_create_pages_render(): void
    {
        $pages = [
            'accounting.journals.create' => 'Accounting/Journals/Form',
            'accounting.accounts.create' => 'Accounting/Accounts/Form',
            'finance.expenses.create' => 'Finance/Expenses/Form',
            'finance.revenues.create' => 'Finance/Revenues/Form',
            'finance.banks.create' => 'Finance/Banks/Form',
            'finance.transfers.create' => 'Finance/Transfers/Form',
            'purchases.suppliers.create' => 'Purchases/Suppliers/Form',
            'purchases.payments.create' => 'Purchases/Payments/Form',
            'inventory.warehouses.create' => 'Inventory/Warehouses/Form',
            'inventory.operations.transfer.create' => 'Inventory/Operations/TransferForm',
            'inventory.operations.adjust.create' => 'Inventory/Operations/AdjustForm',
            'users.create' => 'Users/Form',
            'templates.create' => 'Templates/Create',
            'sales.clients.create' => 'Sales/Clients/Create',
            'pos.registers.create' => 'POS/Registers/Form',
        ];
        foreach ($pages as $name => $component) {
            $this->get(route($name))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($component));
        }
    }

    public function test_edit_pages_render(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'status' => 'active']);
        $customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);
        $account = Account::where('is_parent', false)->first();
        $warehouse = Warehouse::first();

        $this->get(route('purchases.suppliers.edit', $supplier))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Purchases/Suppliers/Form')->where('supplier.id', $supplier->id));
        $this->get(route('accounting.accounts.edit', $account))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Accounting/Accounts/Form')->where('account.id', $account->id));
        $this->get(route('inventory.warehouses.edit', $warehouse))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Inventory/Warehouses/Form'));
        $this->get(route('users.edit', auth()->user()))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Users/Form'));
        $this->get(route('sales.clients.edit', $customer))->assertOk();
    }

    public function test_supplier_is_saved_from_the_page(): void
    {
        $this->post(route('purchases.suppliers.store'), ['name' => 'مورد جديد', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertTrue(Supplier::where('name', 'مورد جديد')->exists());
    }
}
