<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing: work centres, BOM versions, routings, production orders (job costing),
 * process costing, MRP, quality control and subcontracting.
 */
return new class extends Migration
{
    private function qty(Blueprint $t, string $name, bool $nullable = false)
    {
        $c = $t->decimal($name, 18, 4)->default(0);

        return $nullable ? $c->nullable() : $c;
    }

    private function money(Blueprint $t, string $name)
    {
        return $t->decimal($name, 18, 2)->default(0);
    }

    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedSmallInteger('lead_time_days')->default(0)->after('reorder_level');
        });

        Schema::create('work_centers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('type', 20)->default('machine'); // machine, line, labour, workshop
            $t->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $t->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $t->decimal('capacity_hours_per_day', 8, 2)->default(8);
            $t->decimal('efficiency', 5, 2)->default(100);
            $t->decimal('labour_rate_per_hour', 12, 4)->default(0);
            $t->decimal('overhead_rate_per_hour', 12, 4)->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::table('boms', function (Blueprint $t) {
            $t->string('code', 30)->nullable()->after('company_id');
            $t->string('version', 20)->default('1')->after('name');
            $t->boolean('is_default')->default(true)->after('version');
            $t->decimal('scrap_percent', 5, 2)->default(0)->after('output_quantity');
            $t->text('notes')->nullable();
        });
        Schema::table('bom_items', function (Blueprint $t) {
            $t->decimal('scrap_percent', 5, 2)->default(0)->after('quantity');
        });

        Schema::create('routings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('routing_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('routing_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('seq')->default(10);
            $t->string('name');
            $t->foreignId('work_center_id')->constrained('work_centers');
            $t->decimal('setup_minutes', 10, 2)->default(0);
            $t->decimal('run_minutes', 10, 4)->default(0); // per unit
            $t->timestamps();
        });
        Schema::table('boms', function (Blueprint $t) {
            $t->foreignId('routing_id')->nullable()->constrained('routings')->nullOnDelete();
        });

        Schema::create('production_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('order_number', 50);
            $t->foreignId('product_id')->constrained();
            $t->foreignId('bom_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('routing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses');
            $t->foreignId('output_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $this->qty($t, 'quantity');
            $this->qty($t, 'received_quantity');
            $this->qty($t, 'scrap_quantity');
            $t->date('planned_start')->nullable();
            $t->date('planned_end')->nullable();
            $t->date('actual_start')->nullable();
            $t->date('actual_end')->nullable();
            $t->string('status', 20)->default('draft'); // draft, released, in_progress, completed, closed, cancelled
            $t->unsignedTinyInteger('priority')->default(3);
            $t->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $this->money($t, 'material_cost');
            $this->money($t, 'labour_cost');
            $this->money($t, 'overhead_cost');
            $this->money($t, 'transferred_cost');
            $this->money($t, 'variance');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'order_number']);
            $t->index(['company_id', 'status']);
        });
        Schema::create('production_order_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $this->qty($t, 'planned_quantity');
            $this->qty($t, 'issued_quantity');
            $this->qty($t, 'returned_quantity');
            $this->money($t, 'cost');
            $t->timestamps();
        });
        Schema::create('production_order_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('seq')->default(10);
            $t->string('name');
            $t->foreignId('work_center_id')->constrained('work_centers');
            $t->decimal('planned_minutes', 12, 2)->default(0);
            $t->decimal('actual_minutes', 12, 2)->default(0);
            $this->qty($t, 'completed_quantity');
            $t->string('status', 20)->default('pending'); // pending, in_progress, done
            $t->date('planned_date')->nullable();
            $this->money($t, 'labour_cost');
            $this->money($t, 'overhead_cost');
            $t->timestamps();
        });
        Schema::create('production_time_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('operation_id')->nullable()->constrained('production_order_operations')->nullOnDelete();
            $t->foreignId('work_center_id')->constrained('work_centers');
            $t->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->date('date');
            $t->decimal('minutes', 12, 2)->default(0);
            $this->qty($t, 'quantity');
            $this->money($t, 'labour_cost');
            $this->money($t, 'overhead_cost');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('production_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $this->qty($t, 'quantity');
            $this->qty($t, 'scrap_quantity');
            $t->boolean('scrap_abnormal')->default(false);
            $t->decimal('unit_cost', 18, 4)->default(0);
            $this->money($t, 'total_cost');
            $t->string('status', 20)->default('posted'); // posted, pending_qc, rejected
            $t->string('batch_number', 60)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('process_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->foreignId('product_id')->constrained(); // what the stage outputs
            $t->foreignId('previous_stage_id')->nullable()->constrained('process_stages')->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses'); // materials come from / finished goods go to
            $t->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('process_cost_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('process_stage_id')->constrained()->cascadeOnDelete();
            $t->date('date_from');
            $t->date('date_to');
            $t->string('status', 20)->default('open'); // open, closed
            $this->qty($t, 'units_started');
            $this->qty($t, 'units_completed');
            $this->qty($t, 'units_opening_wip');
            $this->qty($t, 'units_closing_wip');
            $t->decimal('closing_material_pct', 5, 2)->default(100);
            $t->decimal('closing_conversion_pct', 5, 2)->default(0);
            foreach (['opening', 'added', 'closing'] as $when) {
                foreach (['ti', 'mat', 'conv'] as $el) {
                    $this->money($t, "{$when}_{$el}");
                }
            }
            foreach (['ti', 'mat', 'conv'] as $el) {
                $this->qty($t, "eu_{$el}");
                $t->decimal("cpu_{$el}", 18, 6)->default(0);
            }
            $this->money($t, 'transferred_out');
            $t->decimal('unit_cost', 18, 6)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('mrp_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->date('run_date');
            $t->unsignedSmallInteger('horizon_days')->default(30);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('mrp_suggestions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('mrp_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->string('type', 15); // purchase, manufacture
            $this->qty($t, 'quantity');
            $this->qty($t, 'gross_demand');
            $this->qty($t, 'on_hand');
            $this->qty($t, 'supply');
            $t->date('need_date')->nullable();
            $t->string('status', 15)->default('new'); // new, converted, dismissed
            $t->string('converted_type', 30)->nullable();
            $t->unsignedBigInteger('converted_id')->nullable();
            $t->timestamps();
        });

        Schema::create('qc_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('applies_to', 15)->default('production'); // production, purchase
            $t->json('checks')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('qc_inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('inspection_number', 50);
            $t->foreignId('qc_plan_id')->nullable()->constrained('qc_plans')->nullOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->nullableMorphs('source');
            $t->date('inspection_date');
            $this->qty($t, 'inspected_quantity');
            $this->qty($t, 'accepted_quantity');
            $this->qty($t, 'rejected_quantity');
            $this->qty($t, 'rework_quantity');
            $t->string('result', 10)->default('pass'); // pass, fail, partial
            $t->string('disposition', 15)->default('accept'); // accept, scrap, rework, return
            $t->json('results')->nullable();
            $t->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'inspection_number']);
        });

        Schema::create('subcontract_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('order_number', 50);
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('bom_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('source_warehouse_id')->constrained('warehouses');
            $t->foreignId('vendor_warehouse_id')->constrained('warehouses'); // stock held at the subcontractor
            $t->foreignId('output_warehouse_id')->constrained('warehouses');
            $this->qty($t, 'quantity');
            $this->qty($t, 'received_quantity');
            $t->decimal('service_price', 18, 4)->default(0); // per unit
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->string('status', 20)->default('draft'); // draft, sent, partially_received, completed, cancelled
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'order_number']);
        });
        Schema::create('subcontract_order_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subcontract_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $this->qty($t, 'planned_quantity');
            $this->qty($t, 'sent_quantity');
            $this->qty($t, 'consumed_quantity');
            $t->timestamps();
        });
        Schema::create('subcontract_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subcontract_order_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $this->qty($t, 'quantity');
            $this->money($t, 'material_cost');
            $this->money($t, 'service_cost');
            $this->money($t, 'total_cost');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['subcontract_receipts', 'subcontract_order_materials', 'subcontract_orders', 'qc_inspections', 'qc_plans', 'mrp_suggestions', 'mrp_runs',
            'process_cost_periods', 'process_stages', 'production_receipts', 'production_time_logs', 'production_order_operations', 'production_order_materials', 'production_orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('boms', fn (Blueprint $t) => $t->dropConstrainedForeignId('routing_id'));
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('routings');
        Schema::table('bom_items', fn (Blueprint $t) => $t->dropColumn('scrap_percent'));
        Schema::table('boms', fn (Blueprint $t) => $t->dropColumn(['code', 'version', 'is_default', 'scrap_percent', 'notes']));
        Schema::dropIfExists('work_centers');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('lead_time_days'));
    }
};
