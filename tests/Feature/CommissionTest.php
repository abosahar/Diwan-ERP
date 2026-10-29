<?php

namespace Tests\Feature;

use App\Models\CommissionPlan;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Sales\CommissionCalculator;
use App\Services\Sales\InvoiceService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $rep;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $owner = $this->userWithRole($company);
        $this->rep = $this->userWithRole($company, 'sales');
        $this->actingAs($owner);
        Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function sale(float $amount): Invoice
    {
        return app(InvoiceService::class)->create(
            ['customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'payment_method' => 'cash', 'payment_status' => 'paid', 'salesperson_id' => $this->rep->id],
            [['item_name' => 'خدمة', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 15]],
        );
    }

    public function test_tiered_commission_is_marginal_and_the_target_bonus_is_added(): void
    {
        $plan = CommissionPlan::create(['name' => 'شرائح', 'type' => 'tiered', 'tiers' => [['from' => 0, 'rate' => 1], ['from' => 10000, 'rate' => 3]], 'target_amount' => 12000, 'bonus_amount' => 500]);
        $this->rep->forceFill(['commission_plan_id' => $plan->id])->save();
        $this->sale(15000);

        $row = collect(app(CommissionCalculator::class)->forMonth(now()->year, now()->month))->firstWhere('user_id', $this->rep->id);
        $this->assertEquals(15000, $row['net']);
        $this->assertEquals(100 + 150, $row['commission']); // 1% of the first 10,000 + 3% of the next 5,000
        $this->assertEquals(500, $row['bonus']);
        $this->assertEquals(750, $row['total']);
    }

    public function test_flat_plan_and_posting_to_payroll_once(): void
    {
        $plan = CommissionPlan::create(['name' => 'ثابتة', 'type' => 'flat', 'rate' => 2]);
        $this->rep->forceFill(['commission_plan_id' => $plan->id])->save();
        $this->sale(5000);
        $calc = app(CommissionCalculator::class);

        try {
            $calc->postToPayroll($this->rep->id, now()->year, now()->month);
            $this->fail('needs a linked employee');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user', $e->errors());
        }

        $employee = Employee::create(['name' => 'مندوب', 'nationality' => 'SA', 'employee_number' => 'E9', 'job_title' => 'مبيعات', 'hire_date' => '2026-01-01', 'contract_type' => 'unlimited', 'status' => 'active', 'basic_salary' => 3000, 'total_salary' => 3000, 'department' => 'مبيعات', 'id_type' => 'national_id', 'user_id' => $this->rep->id]);
        $posting = $calc->postToPayroll($this->rep->id, now()->year, now()->month);

        $this->assertEquals(100, $posting->amount);
        $this->assertDatabaseHas('employee_adjustments', ['employee_id' => $employee->id, 'type' => 'commission', 'amount' => 100]);
        $this->expectException(ValidationException::class);
        $calc->postToPayroll($this->rep->id, now()->year, now()->month);
    }

    public function test_pages_and_plan_assignment(): void
    {
        $this->post(route('sales.commission-plans.store'), ['name' => 'خطة', 'type' => 'flat', 'rate' => 1.5, 'users' => [$this->rep->id]])->assertSessionHasNoErrors();
        $this->assertNotNull($this->rep->fresh()->commission_plan_id);

        $this->get(route('sales.commission-plans.index'))->assertOk();
        $this->get(route('sales.commissions.index'))->assertOk();
        $this->post(route('sales.commission-plans.store'), ['name' => 'شرائح بلا شرائح', 'type' => 'tiered'])->assertSessionHasErrors('tiers');
    }
}
