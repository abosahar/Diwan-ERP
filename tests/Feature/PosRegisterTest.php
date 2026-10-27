<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSetupService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PosRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($company);
        app(TenantManager::class)->setTenant($company);
        $this->actingAs($this->userWithRole($company));
        $this->warehouse = Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_a_register_is_added_and_then_offered_when_opening_a_session(): void
    {
        $this->get(route('pos.index'))->assertInertia(fn (AssertableInertia $p) => $p->component('POS/OpenSession')->has('registers', 0));

        $this->get(route('pos.registers.create'))->assertOk();
        $this->post(route('pos.registers.store'), ['name' => 'كاشير 1', 'code' => 'POS-01', 'warehouse_id' => $this->warehouse->id])->assertSessionHasNoErrors()->assertRedirect(route('pos.registers.index'));

        $this->get(route('pos.index'))->assertInertia(fn (AssertableInertia $p) => $p->has('registers', 1));
        $this->get(route('pos.registers.index'))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('registers', 1));
    }

    public function test_a_code_is_unique_and_a_warehouse_is_required(): void
    {
        PosRegister::create(['name' => 'أ', 'code' => 'POS-01', 'warehouse_id' => $this->warehouse->id]);
        $this->post(route('pos.registers.store'), ['name' => 'ب', 'code' => 'POS-01', 'warehouse_id' => $this->warehouse->id])->assertSessionHasErrors('code');
        $this->post(route('pos.registers.store'), ['name' => 'ب'])->assertSessionHasErrors('warehouse_id');
    }

    public function test_a_register_with_sessions_is_switched_off_not_deleted(): void
    {
        $used = PosRegister::create(['name' => 'مستعمل', 'warehouse_id' => $this->warehouse->id]);
        PosSession::create(['pos_register_id' => $used->id, 'user_id' => auth()->id(), 'opening_cash' => 0, 'status' => 'closed', 'opened_at' => now(), 'closed_at' => now()]);
        $free = PosRegister::create(['name' => 'جديد', 'warehouse_id' => $this->warehouse->id]);

        $this->delete(route('pos.registers.destroy', $used))->assertSessionHas('success');
        $this->assertFalse($used->fresh()->is_active);
        $this->delete(route('pos.registers.destroy', $free));
        $this->assertNull(PosRegister::find($free->id));
    }
}
