<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A copy installed on an office's own computer: the first visit sets the
 * company up in one step (no email verification, no plan limits), then the
 * system opens on the login page, also from other devices of the network.
 */
class LocalInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.local_install' => true]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function setupPayload(array $extra = []): array
    {
        return array_merge([
            'company_name' => 'مؤسسة النخيل', 'country_code' => 'SA', 'currency' => 'SAR', 'name' => 'تركي',
            'email' => 'owner@example.test', 'password' => 'Secret-Pass-2026!', 'password_confirmation' => 'Secret-Pass-2026!',
        ], $extra);
    }

    public function test_the_first_visit_sets_the_company_up_and_the_page_then_closes(): void
    {
        $this->get('/')->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/LocalSetup'));

        $this->post(route('setup'), $this->setupPayload())->assertRedirect(route('dashboard'));

        $company = Company::firstOrFail();
        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame(['active', null, null], [$company->status, $company->subscription_plan_id, $company->trial_ends_at]);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue($owner->isCompanyOwner());
        $this->assertAuthenticatedAs($owner);
        // No verification page: straight into the system.
        $this->get(route('dashboard'))->assertOk();

        auth()->logout();
        app(TenantManager::class)->forget();
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('setup'))->assertNotFound();
        $this->post(route('setup'), $this->setupPayload(['email' => 'second@example.test']))->assertNotFound();
        $this->assertSame(1, Company::count());
    }

    public function test_only_the_computer_it_runs_on_can_set_the_company_up(): void
    {
        // The server answers the whole office network: another device must not become the owner first.
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20']);
        $this->get(route('setup'))->assertForbidden();
        $this->post(route('setup'), $this->setupPayload())->assertForbidden();
        $this->assertSame(0, Company::count());

        $this->withServerVariables(['REMOTE_ADDR' => '::1']);
        $this->get(route('setup'))->assertOk();
        $this->post(route('setup'), $this->setupPayload())->assertRedirect(route('dashboard'));
        $this->assertSame(1, Company::count());
    }

    public function test_the_office_devices_find_the_system_address_in_the_settings(): void
    {
        $this->post(route('setup'), $this->setupPayload())->assertRedirect();

        $this->get(route('settings.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('localAccess')
            ->where('auth.user.email', 'owner@example.test'));
    }

    public function test_the_setup_page_does_not_exist_on_the_platform(): void
    {
        config(['app.local_install' => false]);

        $this->get(route('setup'))->assertNotFound();
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Landing'));
    }
}
