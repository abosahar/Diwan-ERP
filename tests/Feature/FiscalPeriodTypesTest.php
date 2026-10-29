<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingException;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FiscalCalendar;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** A fiscal year can be divided into monthly, quarterly, half-yearly or half-monthly periods. */
class FiscalPeriodTypesTest extends TestCase
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
        $this->actingAs($this->userWithRole($this->company));
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_each_period_type_covers_the_year_without_gaps_or_overlaps(): void
    {
        $expected = ['monthly' => 12, 'quarterly' => 4, 'semiannual' => 2, 'semimonthly' => 24];
        $start = 2031;

        foreach ($expected as $type => $count) {
            $this->post(route('accounting.fiscal-years.store'), ['start_date' => "{$start}-01-01", 'period_type' => $type])->assertSessionHasNoErrors();
            $year = FiscalYear::whereDate('start_date', "{$start}-01-01")->firstOrFail();
            $this->assertSame($type, $year->period_type);

            $periods = $year->periods()->get();
            $this->assertCount($count, $periods, $type);
            $this->assertSame("{$start}-01-01", $periods->first()->start_date->toDateString());
            $this->assertSame("{$start}-12-31", $periods->last()->end_date->toDateString());
            foreach ($periods->slice(1)->values() as $i => $period) {
                $this->assertSame(
                    $periods[$i]->end_date->copy()->addDay()->toDateString(),
                    $period->start_date->toDateString(),
                    "{$type}: gap or overlap before {$period->name}"
                );
            }

            $year->periods()->delete();
            $year->delete();
            $start++;
        }
    }

    public function test_period_names_and_half_month_bounds(): void
    {
        $calendar = app(FiscalCalendar::class);
        $year = $calendar->createYear(Carbon::parse('2033-01-01'), $this->company->id, 'semimonthly');
        $this->assertSame('2033-02-A', $year->periods[2]->name);
        $this->assertSame('2033-02-15', $year->periods[2]->end_date->toDateString());
        $this->assertSame('2033-02-16', $year->periods[3]->start_date->toDateString());
        $this->assertSame('2033-02-28', $year->periods[3]->end_date->toDateString());

        $quarters = $calendar->createYear(Carbon::parse('2034-01-01'), $this->company->id, 'quarterly');
        $this->assertSame(['2034-Q1', '2034-Q2', '2034-Q3', '2034-Q4'], $quarters->periods->pluck('name')->all());
    }

    public function test_a_closed_half_month_blocks_posting_only_inside_it(): void
    {
        $calendar = app(FiscalCalendar::class);
        $year = $calendar->createYear(Carbon::parse('2035-01-01'), $this->company->id, 'semimonthly');
        $first = $year->periods->first(); // 2035-01-01 .. 2035-01-15
        $first->update(['status' => 'closed']);

        $this->assertSame($first->id, $calendar->periodFor('2035-01-10', $this->company->id)->id);
        $this->assertNotSame($first->id, $calendar->periodFor('2035-01-20', $this->company->id)->id);
        $calendar->assertOpen('2035-01-20', $this->company->id);

        $this->expectException(AccountingException::class);
        $calendar->assertOpen('2035-01-10', $this->company->id);
    }

    public function test_default_type_is_monthly_and_the_page_lists_the_types(): void
    {
        $this->post(route('accounting.fiscal-years.store'), ['start_date' => '2036-01-01'])->assertSessionHasNoErrors();
        $this->assertSame('monthly', FiscalYear::whereDate('start_date', '2036-01-01')->value('period_type'));
        $this->assertSame(12, FiscalPeriod::where('fiscal_year_id', FiscalYear::whereDate('start_date', '2036-01-01')->value('id'))->count());

        $this->get(route('accounting.fiscal-years.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('periodTypes', 4));
        $this->post(route('accounting.fiscal-years.store'), ['start_date' => '2037-01-01', 'period_type' => 'weekly'])->assertSessionHasErrors('period_type');
    }
}
