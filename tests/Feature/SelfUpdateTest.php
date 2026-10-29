<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Accounting\AccountingSetupService;
use App\Services\SelfUpdate;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SelfUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('self-update:latest');
        File::deleteDirectory(storage_path('app/update-queue'));
        @unlink(storage_path('app/update-status.json'));
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company));
    }

    protected function release(string $tag): void
    {
        Cache::forget('self-update:latest');
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => $tag, 'body' => 'notes', 'html_url' => 'https://example.test'])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/update-queue'));
        @unlink(storage_path('app/update-status.json'));
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_the_banner_data_appears_only_on_local_installs(): void
    {
        config(['app.local_install' => false]);
        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $p) => $p->where('update', null));

        config(['app.local_install' => true]);
        $this->release('v9.9.9');
        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $p) => $p->where('update.available', true)->where('update.latest.version', '9.9.9'));
    }

    public function test_applying_drops_a_request_for_the_update_script(): void
    {
        config(['app.local_install' => true]);
        $this->release('v9.9.9');
        $this->postJson(route('system.update.apply'))->assertOk()->assertJsonPath('status.state', 'requested');
        $this->assertFileExists(storage_path('app/update-queue/request'));
        $this->assertTrue(app(SelfUpdate::class)->running());
    }

    public function test_releases_come_from_the_configured_repository(): void
    {
        config(['app.repository' => null]);
        $this->release('v9.9.9');
        app(SelfUpdate::class)->latest();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/'.SelfUpdate::DEFAULT_REPO.'/releases/latest');

        config(['app.repository' => 'someone/Diwan-Fork']);
        $this->release('v9.9.9');
        app(SelfUpdate::class)->latest();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/someone/Diwan-Fork/releases/latest');

        // Anything that is not owner/name falls back to the default rather than reaching another URL.
        config(['app.repository' => 'evil.example/x/../y']);
        $this->assertSame(SelfUpdate::DEFAULT_REPO, SelfUpdate::repo());
    }

    public function test_nothing_happens_when_already_current(): void
    {
        config(['app.local_install' => true]);
        $this->release('v'.app(SelfUpdate::class)->current());
        $this->postJson(route('system.update.apply'))->assertStatus(422);
    }

    public function test_it_is_off_outside_local_installs(): void
    {
        config(['app.local_install' => false]);
        $this->postJson(route('system.update.apply'))->assertNotFound();
    }

    public function test_a_finished_update_is_forgotten_after_a_few_minutes(): void
    {
        $file = storage_path('app/update-status.json');
        file_put_contents($file, json_encode(['state' => 'done', 'to' => '1.4.0', 'at' => time() - 60]));
        $this->assertSame('done', app(SelfUpdate::class)->status()['state']);

        file_put_contents($file, json_encode(['state' => 'done', 'to' => '1.4.0', 'at' => time() - 3600]));
        $this->assertNull(app(SelfUpdate::class)->status());

        file_put_contents($file, json_encode(['state' => 'failed', 'message' => 'x', 'at' => time() - 3600]));
        $this->assertSame('failed', app(SelfUpdate::class)->status()['state']);
    }
}
