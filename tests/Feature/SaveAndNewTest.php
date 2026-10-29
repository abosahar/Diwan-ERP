<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Nationality;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «حفظ وإضافة جديد»: the X-Save-And-New header sends a successful create back to the empty create page. */
class SaveAndNewTest extends TestCase
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

    public function test_a_save_and_new_post_returns_to_the_create_page(): void
    {
        $this->post(route('hr.nationalities.store'), ['code' => 'ZW', 'name_ar' => 'زمبابوي'], ['X-Save-And-New' => '1'])
            ->assertRedirect(route('hr.nationalities.create'));
        $this->assertTrue(Nationality::where('code', 'ZW')->exists());

        // Without the header the controller's own redirect applies.
        $this->post(route('hr.nationalities.store'), ['code' => 'GH', 'name_ar' => 'غاني'])->assertRedirect(route('hr.nationalities.index'));
    }

    public function test_validation_errors_stay_on_the_form(): void
    {
        $response = $this->from(route('hr.nationalities.create'))->post(route('hr.nationalities.store'), ['code' => 'ZZZ', 'name_ar' => ''], ['X-Save-And-New' => '1']);
        $response->assertSessionHasErrors(['code', 'name_ar']);
        $this->assertSame(route('hr.nationalities.create'), $response->headers->get('Location'));
    }
}
