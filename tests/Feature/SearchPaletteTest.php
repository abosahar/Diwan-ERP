<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\AccountMap;
use App\Services\Accounting\PostingService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The global search palette: scoping by kind, record codes, new kinds, permissions. */
class SearchPaletteTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
        $this->actingAs($this->userWithRole($this->company));
        $map = app(AccountMap::class);
        app(PostingService::class)->post(['date' => '2026-06-10', 'description' => 'دفع فاتورة الكهرباء', 'reference' => 'EL-1', 'lines' => [
            ['account_id' => $map->id('general_expense'), 'debit' => 50], ['account_id' => $map->id('default_cash'), 'credit' => 50],
        ]]);
        Customer::create(['name' => 'شركة الكهرباء الحديثة', 'code' => 'CUS000001', 'customer_type' => 'commercial', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_results_carry_the_record_code_and_journals_are_searchable(): void
    {
        $groups = collect($this->getJson(route('search', ['q' => 'الكهرباء']))->assertOk()->json('groups'))->keyBy('key');

        $this->assertEqualsCanonicalizing(['customers', 'journals', 'accounts'], $groups->keys()->all());
        $this->assertSame(JournalEntry::first()->entry_number, $groups['journals']['items'][0]['code']);
        $this->assertSame(route('accounting.journals.show', JournalEntry::first()->id), $groups['journals']['items'][0]['url']);
        $this->assertSame('CUS000001', $groups['customers']['items'][0]['code']);
    }

    public function test_kind_narrows_the_search_to_one_group(): void
    {
        $groups = $this->getJson(route('search', ['q' => 'الكهرباء', 'kind' => 'journals']))->assertOk()->json('groups');
        $this->assertSame(['journals'], array_column($groups, 'key'));

        $this->assertSame([], $this->getJson(route('search', ['q' => 'الكهرباء', 'kind' => 'projects']))->json('groups'));
    }

    public function test_the_palette_lists_the_kinds_the_user_may_search(): void
    {
        $kinds = collect($this->getJson(route('search', ['q' => '']))->assertOk()->json('kinds'))->pluck('key');
        $this->assertTrue($kinds->contains('journals'));
        $this->assertTrue($kinds->contains('customers'));

        // A cashier cannot search the ledger.
        $this->actingAs($this->userWithRole($this->company, 'cashier'));
        $kinds = collect($this->getJson(route('search', ['q' => 'الكهرباء']))->json('kinds'))->pluck('key');
        $this->assertFalse($kinds->contains('journals'));
        $keys = collect($this->getJson(route('search', ['q' => 'الكهرباء']))->json('groups'))->pluck('key');
        $this->assertFalse($keys->contains('journals'));
    }
}
