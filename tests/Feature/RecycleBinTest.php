<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quotation;
use App\Services\Accounting\AccountingSetupService;
use App\Services\RecycleBin;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecycleBinTest extends TestCase
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

    protected function quotation(string $status = 'draft'): Quotation
    {
        $customer = Customer::create(['name' => 'عميل', 'customer_type' => 'individual', 'status' => 'active']);

        return Quotation::create(['customer_id' => $customer->id, 'quotation_number' => 'Q-'.uniqid(), 'quotation_date' => now(), 'valid_until' => now()->addDays(10), 'subtotal' => 100, 'discount_amount' => 0, 'tax_rate' => 15, 'tax_amount' => 15, 'total' => 115, 'status' => $status]);
    }

    public function test_deleting_moves_to_the_bin_and_can_be_restored(): void
    {
        $q = $this->quotation();

        $this->delete(route('sales.quotations.destroy', $q->id))->assertRedirect();
        $this->assertSoftDeleted($q);
        $this->get(route('recycle-bin.index'))->assertOk()->assertInertia(fn ($p) => $p->component('RecycleBin/Index')->has('rows', 1));

        $this->post(route('recycle-bin.restore', ['quotation', $q->id]))->assertRedirect();
        $this->assertNotSoftDeleted($q);
    }

    public function test_a_converted_quotation_cannot_be_deleted(): void
    {
        $q = $this->quotation('converted');

        $this->delete(route('sales.quotations.destroy', $q->id))->assertSessionHasErrors('document');
        $this->assertNotSoftDeleted($q);
    }

    public function test_documents_older_than_ninety_days_are_erased(): void
    {
        $old = $this->quotation();
        $recent = $this->quotation();
        $old->delete();
        $recent->delete();
        Quotation::onlyTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(RecycleBin::RETENTION_DAYS + 1)]);

        $this->assertSame(1, app(RecycleBin::class)->purgeExpired());
        $this->assertDatabaseMissing('quotations', ['id' => $old->id]);
        $this->assertDatabaseHas('quotations', ['id' => $recent->id]);
    }
}
