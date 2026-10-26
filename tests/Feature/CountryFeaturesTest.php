<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Saudi-only features (ZATCA, zakat, GOSI, Mudad, Saudization) exist only
 * while the branch being worked in is Saudi.
 */
class CountryFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['currency' => 'YER', 'country_code' => 'YE', 'tax_number' => '1234567']);
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
        $this->owner = $this->userWithRole($this->company);
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_a_yemeni_company_has_no_saudi_features(): void
    {
        Branch::create(['name_ar' => 'صنعاء', 'code' => 'SAN', 'is_main' => true, 'is_active' => true, 'country_code' => 'YE']);

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('features', fn ($f) => ! array_intersect(['zatca', 'zakat', 'gosi', 'wps', 'saudization'], $f->all())));
        foreach (['zatca.index', 'hr.reports.gosi', 'hr.reports.saudization'] as $name) {
            $this->get(route($name))->assertNotFound();
        }
        $this->post(route('accounting.period-end.zakat'), [])->assertNotFound();
    }

    public function test_a_multi_country_company_gets_saudi_features_in_its_saudi_branch(): void
    {
        $sanaa = Branch::create(['name_ar' => 'صنعاء', 'code' => 'SAN', 'is_main' => true, 'is_active' => true, 'country_code' => 'YE']);
        $riyadh = Branch::create(['name_ar' => 'الرياض', 'code' => 'RUH', 'is_active' => true, 'country_code' => 'SA']);

        $this->post(route('context.branch'), ['branch_id' => $riyadh->id]);
        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('features', fn ($f) => in_array('zatca', $f->all()) && in_array('gosi', $f->all())));
        $this->get(route('hr.reports.gosi'))->assertOk();

        $this->post(route('context.branch'), ['branch_id' => $sanaa->id]);
        $this->get(route('hr.reports.gosi'))->assertNotFound();
    }
}
