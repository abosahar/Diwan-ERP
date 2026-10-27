<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\ProcessStage;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\QcPlan;
use App\Models\Routing;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Inventory\StockService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** The manufacturing screens and the routes behind them. */
class ManufacturingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        app(AccountingSetupService::class)->provision($this->company);
        app(TenantManager::class)->setTenant($this->company);
        $this->withoutVite();
        $this->actingAs($this->userWithRole($this->company));
        $this->warehouse = Warehouse::create(['name_ar' => 'رئيسي', 'code' => 'W1', 'is_main' => true, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        parent::tearDown();
    }

    public function test_every_list_and_create_page_renders(): void
    {
        $pages = [
            'manufacturing.dashboard' => 'Manufacturing/Dashboard',
            'manufacturing.work-centers.index' => 'Manufacturing/WorkCenters/Index', 'manufacturing.work-centers.create' => 'Manufacturing/WorkCenters/Form',
            'manufacturing.boms.index' => 'Manufacturing/Boms/Index', 'manufacturing.boms.create' => 'Manufacturing/Boms/Form',
            'manufacturing.routings.index' => 'Manufacturing/Routings/Index', 'manufacturing.routings.create' => 'Manufacturing/Routings/Form',
            'manufacturing.orders.index' => 'Manufacturing/Orders/Index', 'manufacturing.orders.create' => 'Manufacturing/Orders/Create',
            'manufacturing.shop-floor.index' => 'Manufacturing/ShopFloor/Index',
            'manufacturing.stages.index' => 'Manufacturing/Process/Stages/Index', 'manufacturing.stages.create' => 'Manufacturing/Process/Stages/Form',
            'manufacturing.periods.index' => 'Manufacturing/Process/Periods/Index', 'manufacturing.periods.create' => 'Manufacturing/Process/Periods/Create',
            'manufacturing.mrp.index' => 'Manufacturing/Mrp/Index', 'manufacturing.capacity.index' => 'Manufacturing/Capacity/Index',
            'manufacturing.plans.index' => 'Manufacturing/Quality/Plans/Index', 'manufacturing.plans.create' => 'Manufacturing/Quality/Plans/Form',
            'manufacturing.inspections.index' => 'Manufacturing/Quality/Inspections/Index', 'manufacturing.inspections.create' => 'Manufacturing/Quality/Inspections/Create',
            'manufacturing.subcontracts.index' => 'Manufacturing/Subcontracts/Index', 'manufacturing.subcontracts.create' => 'Manufacturing/Subcontracts/Create',
            'manufacturing.reports.index' => 'Manufacturing/Reports/Index',
        ];
        foreach ($pages as $name => $component) {
            $this->get(route($name))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($component));
        }
        foreach (['summary', 'wip', 'materials', 'utilization'] as $report) {
            $this->get(route('manufacturing.reports.index', ['report' => $report]))->assertOk();
        }
    }

    public function test_edit_pages_render(): void
    {
        $product = Product::create(['name_ar' => 'منتج', 'sku' => 'E-1', 'selling_price' => 1, 'purchase_price' => 0, 'track_inventory' => true, 'is_active' => true]);
        $center = WorkCenter::create(['code' => 'E1', 'name' => 'مركز', 'type' => 'machine']);
        $routing = Routing::create(['code' => 'ER', 'name' => 'مسار']);
        $routing->operations()->create(['seq' => 10, 'name' => 'عملية', 'work_center_id' => $center->id]);
        $bom = Bom::create(['product_id' => $product->id, 'name' => 'قائمة', 'output_quantity' => 1, 'is_active' => true]);
        $bom->items()->create(['component_id' => $product->id, 'quantity' => 1]);
        $stage = ProcessStage::create(['code' => 'ES', 'name' => 'مرحلة', 'sequence' => 1, 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id]);
        $plan = QcPlan::create(['name' => 'خطة', 'applies_to' => 'production', 'checks' => [['name' => 'بند', 'type' => 'passfail']], 'is_active' => true]);

        $this->get(route('manufacturing.work-centers.edit', $center))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Manufacturing/WorkCenters/Form'));
        $this->get(route('manufacturing.routings.edit', $routing))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Manufacturing/Routings/Form')->has('routing.operations', 1));
        $this->get(route('manufacturing.boms.edit', $bom))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Manufacturing/Boms/Form')->has('bom.items', 1));
        $this->get(route('manufacturing.stages.edit', $stage))->assertOk();
        $this->get(route('manufacturing.plans.edit', $plan))->assertOk();
        // A work centre used by a routing is switched off, not deleted.
        $this->delete(route('manufacturing.work-centers.destroy', $center))->assertSessionHas('success');
        $this->assertFalse($center->fresh()->is_active);
    }

    public function test_the_order_cycle_through_the_screens(): void
    {
        $a = Product::create(['name_ar' => 'خام', 'sku' => 'RM-1', 'selling_price' => 1, 'purchase_price' => 0, 'track_inventory' => true, 'is_active' => true]);
        $fg = Product::create(['name_ar' => 'تام', 'sku' => 'FG-1', 'selling_price' => 50, 'purchase_price' => 0, 'track_inventory' => true, 'is_active' => true]);
        app(StockService::class)->receive(['product_id' => $a->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 100, 'unit_cost' => 4, 'type' => 'purchase', 'date' => now()]);

        $this->post(route('manufacturing.work-centers.store'), ['code' => 'WC1', 'name' => 'مكبس', 'type' => 'machine', 'capacity_hours_per_day' => 8, 'efficiency' => 100, 'labour_rate_per_hour' => 30, 'overhead_rate_per_hour' => 10])->assertSessionHasNoErrors();
        $center = WorkCenter::firstOrFail();
        $this->post(route('manufacturing.work-centers.store'), ['code' => 'WC1', 'name' => 'مكرر', 'type' => 'machine', 'capacity_hours_per_day' => 8, 'efficiency' => 100, 'labour_rate_per_hour' => 0, 'overhead_rate_per_hour' => 0])->assertSessionHasErrors('code');

        $this->post(route('manufacturing.routings.store'), ['code' => 'R1', 'name' => 'مسار', 'operations' => [['seq' => 10, 'name' => 'كبس', 'work_center_id' => $center->id, 'setup_minutes' => 0, 'run_minutes' => 3]]])->assertSessionHasNoErrors();
        $routingId = Routing::firstOrFail()->id;
        $this->post(route('manufacturing.boms.store'), ['product_id' => $fg->id, 'name' => 'قائمة', 'version' => '1', 'is_default' => true, 'output_quantity' => 1, 'scrap_percent' => 0, 'routing_id' => $routingId, 'items' => [['component_id' => $a->id, 'quantity' => 2]]])->assertSessionHasNoErrors();
        // A product cannot be its own component.
        $this->post(route('manufacturing.boms.store'), ['product_id' => $fg->id, 'name' => 'حلقة', 'version' => '2', 'output_quantity' => 1, 'scrap_percent' => 0, 'items' => [['component_id' => $fg->id, 'quantity' => 1]]])->assertSessionHasErrors();

        $this->post(route('manufacturing.orders.store'), ['product_id' => $fg->id, 'quantity' => 10, 'warehouse_id' => $this->warehouse->id])->assertSessionHasNoErrors();
        $order = ProductionOrder::firstOrFail();
        $this->get(route('manufacturing.orders.show', $order))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Manufacturing/Orders/Show')->has('order.materials', 1)->has('order.operations', 1));

        $this->post(route('manufacturing.orders.release', $order))->assertSessionHasNoErrors();
        $this->post(route('manufacturing.orders.issue-all', $order))->assertSessionHasNoErrors();
        $this->post(route('manufacturing.orders.time', $order), ['work_center_id' => $center->id, 'minutes' => 30, 'quantity' => 10])->assertSessionHasNoErrors();
        $this->post(route('manufacturing.orders.receive', $order), ['quantity' => 10])->assertSessionHasNoErrors();
        $this->post(route('manufacturing.orders.close', $order))->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('closed', $order->status);
        $this->assertSame(10.0, (float) InventoryStock::where('product_id', $fg->id)->sum('quantity'));
        $this->assertSame(80.0, (float) $order->material_cost); // 20 x 4
        $this->assertSame(20.0, (float) $order->labour_cost + (float) $order->overhead_cost); // half an hour at 40/h
        $this->get(route('manufacturing.orders.print', $order))->assertOk();
    }

    public function test_a_user_without_the_module_permission_is_refused(): void
    {
        $this->actingAs($this->userWithRole($this->company, 'cashier'));
        $this->get(route('manufacturing.orders.index'))->assertForbidden();
        $this->post(route('manufacturing.work-centers.store'), [])->assertForbidden();
    }

    public function test_the_production_manager_role_works_the_module_only(): void
    {
        $this->actingAs($this->userWithRole($this->company, 'production_manager'));
        $this->get(route('manufacturing.orders.index'))->assertOk();
        $this->get(route('accounting.journals.index'))->assertForbidden();
    }
}
