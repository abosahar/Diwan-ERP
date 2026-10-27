<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Services\Manufacturing\ProductionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The workshop's screen: what is open to work on, and a quick way to report time and output. */
class ShopFloorController extends Controller
{
    public function index(): Response
    {
        $operations = ProductionOrderOperation::with(['order.product:id,name_ar', 'workCenter:id,code,name'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['released', 'in_progress']))
            ->where('status', '!=', 'done')->orderBy('planned_date')->orderBy('seq')->get()
            ->map(fn ($o) => [
                'id' => $o->id, 'seq' => $o->seq, 'name' => $o->name, 'status' => $o->status, 'planned_minutes' => (float) $o->planned_minutes, 'actual_minutes' => (float) $o->actual_minutes,
                'completed' => (float) $o->completed_quantity, 'planned_date' => $o->planned_date?->toDateString(), 'work_center' => $o->workCenter?->name, 'work_center_id' => $o->work_center_id,
                'order_id' => $o->production_order_id, 'order_number' => $o->order?->order_number, 'product' => $o->order?->product?->name_ar, 'quantity' => (float) $o->order?->quantity,
            ])->values();

        return Inertia::render('Manufacturing/ShopFloor/Index', [
            'operations' => $operations,
            'employees' => Employee::where('status', '!=', 'terminated')->orderBy('name')->limit(300)->get(['id', 'name']),
        ]);
    }

    public function log(Request $request, ProductionService $production)
    {
        $data = $request->validate([
            'operation_id' => 'required|integer', 'minutes' => 'required|numeric|min:0.01', 'quantity' => 'nullable|numeric|min:0', 'employee_id' => 'nullable|tenant_exists:employees,id',
        ]);
        $op = ProductionOrderOperation::findOrFail($data['operation_id']);
        $order = ProductionOrder::findOrFail($op->production_order_id);
        $production->logTime($order, $data + ['work_center_id' => $op->work_center_id, 'date' => now()->toDateString()]);

        return back()->with('success', 'سُجّل الوقت والإنتاج.');
    }
}
