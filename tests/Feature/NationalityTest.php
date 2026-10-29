<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Nationality;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NationalityTest extends TestCase
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

    public function test_a_company_starts_with_the_standard_list_and_the_page_counts_employees(): void
    {
        $this->assertGreaterThan(25, Nationality::count());
        Employee::create(['name' => 'موظف', 'nationality' => 'EG', 'employee_number' => 'E1', 'job_title' => 'محاسب', 'hire_date' => '2026-01-01', 'contract_type' => 'unlimited', 'status' => 'active', 'basic_salary' => 1000, 'total_salary' => 1000, 'department' => 'عام', 'id_type' => 'iqama']);

        $this->get(route('hr.nationalities.index'))->assertOk()->assertInertia(fn ($page) => $page->component('HR/Nationalities/Index')->where('stats.inactive', 0));
        $rows = collect($this->get(route('hr.nationalities.index'))->viewData('page')['props']['nationalities']);
        $this->assertSame(1, $rows->firstWhere('code', 'EG')['employees']);
    }

    public function test_add_edit_disable_and_json_create(): void
    {
        $this->post(route('hr.nationalities.store'), ['code' => 'zw', 'name_ar' => 'زمبابوي', 'name_en' => 'Zimbabwean'])->assertSessionHasNoErrors();
        $zw = Nationality::where('code', 'ZW')->firstOrFail();
        $this->post(route('hr.nationalities.store'), ['code' => 'ZW', 'name_ar' => 'مكرر'])->assertSessionHasErrors('code');

        $this->put(route('hr.nationalities.update', $zw->id), ['code' => 'ZW', 'name_ar' => 'زمبابوي', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($zw->fresh()->is_active);

        $json = $this->postJson(route('hr.nationalities.store'), ['code' => 'GH', 'name_ar' => 'غاني'])->assertCreated();
        $this->assertSame('GH', $json->json('record.code'));
        $this->assertSame('غاني', $json->json('record.label'));
    }

    public function test_an_employee_needs_an_active_nationality(): void
    {
        Nationality::where('code', 'EG')->update(['is_active' => false]);
        $data = ['name' => 'موظف', 'nationality' => 'EG', 'id_type' => 'iqama', 'job_title' => 'محاسب', 'hire_date' => '2026-01-01', 'contract_type' => 'unlimited', 'basic_salary' => 1000, 'status' => 'active'];

        $this->post(route('hr.employees.store'), $data)->assertSessionHasErrors('nationality');
        $this->post(route('hr.employees.store'), ['nationality' => 'SA'] + $data)->assertSessionHasNoErrors();
    }
}
