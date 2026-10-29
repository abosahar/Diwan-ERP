<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Accounting\AccountingSetupService;
use App\Services\SelfUpdate;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company, 'cashier'));
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_whats_new_lists_releases_newest_first(): void
    {
        $this->get(route('whats-new.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('WhatsNew/Index')->where('releases.0.version', config('release_notes.0.version')));
    }

    public function test_feature_requests_page_points_to_the_developer_repository(): void
    {
        $this->get(route('feature-requests.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('FeatureRequests/Index')->where('repo', SelfUpdate::DEFAULT_REPO));

        config(['app.repository' => 'someone/Diwan-Fork']);
        $this->get(route('feature-requests.index'))
            ->assertInertia(fn ($page) => $page->where('repo', 'someone/Diwan-Fork'));
    }
}
