<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FiscalCalendar;
use App\Services\ActiveBranch;
use App\Services\ActiveFiscalYear;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The sidebar's «سياق العمل» card: branch and fiscal year are applied together
 * (context.update), only to values the user may choose, and nothing changes
 * when any of them is refused.
 */
class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Branch $riyadh;

    protected Branch $jeddah;

    protected Branch $dammam;

    protected FiscalYear $current;

    protected FiscalYear $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->company = Company::factory()->create(['currency' => 'SAR', 'country_code' => 'SA', 'tax_number' => '310122393500003']);
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
        $this->actingAs($this->userWithRole($this->company));
        $this->riyadh = Branch::create(['name_ar' => 'الرياض', 'code' => 'RUH', 'is_main' => true, 'is_active' => true]);
        $this->jeddah = Branch::create(['name_ar' => 'جدة', 'code' => 'JED', 'is_active' => true]);
        $this->dammam = Branch::create(['name_ar' => 'الدمام', 'code' => 'DMM', 'is_active' => true]);
        app(FiscalCalendar::class)->createYear(Carbon::parse('2025-01-01'), $this->company->id);
        $this->current = FiscalYear::whereDate('start_date', '2026-01-01')->firstOrFail();
        $this->previous = FiscalYear::whereDate('start_date', '2025-01-01')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    protected function clerk(array $branches): User
    {
        $clerk = $this->userWithRole($this->company, 'sales');
        $clerk->forceFill(['branch_id' => $branches[0]->id])->save();
        $clerk->branches()->sync(collect($branches)->pluck('id'));

        return $clerk->fresh();
    }

    protected function assertContext(int|string|null $branch, ?int $year): void
    {
        $this->assertSame($branch, session(ActiveBranch::SESSION_KEY));
        $this->assertSame($year, session(ActiveFiscalYear::SESSION_KEY));
    }

    public function test_branch_and_fiscal_year_are_applied_together(): void
    {
        $this->post(route('context.update'), ['branch_id' => $this->jeddah->id, 'fiscal_year_id' => $this->previous->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'جدة') && str_contains($message, $this->previous->name));
        $this->assertContext($this->jeddah->id, $this->previous->id);

        // Every page now follows the new context (the card reads these props).
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant.name', $this->company->name)
            ->where('branchSwitcher.active', $this->jeddah->id)
            ->where('fiscalSwitcher.active', $this->previous->id));

        // Viewing all branches is a choice for those who may use several.
        $this->post(route('context.update'), ['branch_id' => 'all'])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'كل الفروع'));
        $this->assertContext(ActiveBranch::ALL, $this->previous->id);
    }

    public function test_only_the_values_sent_change(): void
    {
        $this->post(route('context.update'), ['fiscal_year_id' => $this->previous->id])->assertSessionHasNoErrors();
        $this->assertContext(null, $this->previous->id);

        // Nothing sent: nothing saved and no message.
        $this->post(route('context.update'), [])->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('success');
        $this->assertContext(null, $this->previous->id);
    }

    public function test_nothing_changes_when_one_choice_is_refused(): void
    {
        $this->actingAs($this->clerk([$this->riyadh, $this->jeddah]));

        // The branch is not theirs: the (valid) fiscal year is not applied either.
        $this->post(route('context.update'), ['branch_id' => $this->dammam->id, 'fiscal_year_id' => $this->previous->id])
            ->assertSessionHasErrors(['context' => 'لا تملك صلاحية العمل على هذا الفرع.'])
            ->assertSessionMissing('success');
        $this->assertContext(null, null);

        // Another company's fiscal year is unknown here: the (valid) branch is not applied either.
        $other = Company::factory()->create();
        $foreign = app(TenantManager::class)->runAs($other, fn () => FiscalYear::create(['name' => '2030', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'status' => 'open']));
        $this->assertSame($other->id, $foreign->company_id);
        $this->post(route('context.update'), ['branch_id' => $this->jeddah->id, 'fiscal_year_id' => $foreign->id])
            ->assertSessionHasErrors(['context' => 'السنة المالية غير موجودة.']);
        $this->assertContext(null, null);

        // Malformed values never reach the session.
        $this->post(route('context.update'), ['branch_id' => 'jeddah'])->assertSessionHasErrors('branch_id');
        $this->assertContext(null, null);
    }

    public function test_a_limited_user_is_offered_only_their_branches(): void
    {
        $this->actingAs($this->clerk([$this->riyadh, $this->jeddah]));
        $this->get(route('sales.invoices.create'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('branchSwitcher.items', 2)
            ->where('branchSwitcher.items.0.id', $this->riyadh->id)
            ->where('branchSwitcher.items.1.id', $this->jeddah->id)
            ->where('branchSwitcher.can_all', true)
            ->has('fiscalSwitcher.items', 2));

        // One branch: nothing to choose, and no "all branches" view.
        $this->actingAs($this->clerk([$this->dammam]));
        $this->get(route('sales.invoices.create'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('branchSwitcher.items', 1)
            ->where('branchSwitcher.active', $this->dammam->id)
            ->where('branchSwitcher.can_all', false));
        $this->post(route('context.update'), ['branch_id' => 'all'])->assertSessionHasErrors('context');
        $this->post(route('context.update'), ['branch_id' => $this->riyadh->id])->assertSessionHasErrors('context');
        $this->assertContext(null, null);
    }

    public function test_a_record_page_of_the_old_branch_is_left_for_the_dashboard(): void
    {
        $record = url('/sales/invoices/12');

        // A record may belong to the branch being left.
        $this->from($record)->post(route('context.update'), ['branch_id' => $this->jeddah->id])->assertRedirect(route('dashboard'));

        // The fiscal year does not own records: back to the same page.
        $this->from($record)->post(route('context.update'), ['fiscal_year_id' => $this->previous->id])->assertRedirect($record);
        $this->from(url('/sales/invoices'))->post(route('context.update'), ['branch_id' => $this->dammam->id])->assertRedirect(url('/sales/invoices'));
    }
}
