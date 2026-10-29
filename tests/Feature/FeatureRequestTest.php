<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FeatureRequest;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_a_user_submits_a_request_and_it_starts_with_their_own_vote(): void
    {
        $this->actingAs($this->userWithRole($this->company, 'cashier'));

        $this->post(route('feature-requests.store'), ['kind' => 'feature', 'title' => 'طباعة الباركود', 'description' => 'أريد طباعة ملصقات الباركود'])->assertRedirect();

        $request = FeatureRequest::firstOrFail();
        $this->assertSame(1, $request->votes_count);
        $this->assertSame('new', $request->status);
        $this->get(route('feature-requests.index'))->assertOk()->assertInertia(fn ($page) => $page->component('FeatureRequests/Index')->where('requests.data.0.voted', true));
    }

    public function test_votes_toggle_and_count_distinct_users(): void
    {
        $owner = $this->userWithRole($this->company);
        $this->actingAs($owner)->post(route('feature-requests.store'), ['kind' => 'improvement', 'title' => 'تحسين', 'description' => 'وصف']);
        $request = FeatureRequest::firstOrFail();

        $other = $this->userWithRole($this->company, 'cashier');
        $this->actingAs($other)->post(route('feature-requests.vote', $request->id));
        $this->assertSame(2, $request->fresh()->votes_count);

        $this->actingAs($other)->post(route('feature-requests.vote', $request->id));
        $this->assertSame(1, $request->fresh()->votes_count);
    }

    public function test_only_managers_change_the_status(): void
    {
        $this->actingAs($this->userWithRole($this->company, 'cashier'));
        $request = FeatureRequest::create(['kind' => 'feature', 'title' => 'ط', 'description' => 'و', 'status' => 'new']);

        $this->put(route('feature-requests.update', $request->id), ['status' => 'done'])->assertForbidden();

        $this->actingAs($this->userWithRole($this->company))
            ->put(route('feature-requests.update', $request->id), ['status' => 'done', 'released_in' => '1.5.0', 'admin_note' => 'تم'])
            ->assertRedirect();
        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame('1.5.0', $request->fresh()->released_in);
    }

    public function test_whats_new_lists_releases_newest_first(): void
    {
        $this->actingAs($this->userWithRole($this->company, 'cashier'));

        $this->get(route('whats-new.index'))->assertOk()->assertInertia(fn ($page) => $page->component('WhatsNew/Index')->where('releases.0.version', config('release_notes.0.version')));
    }
}
