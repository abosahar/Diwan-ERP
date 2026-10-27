<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\MrpRun;
use App\Models\MrpSuggestion;
use App\Services\Manufacturing\CapacityPlanner;
use App\Services\Manufacturing\MrpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MrpController extends Controller
{
    public function __construct(protected MrpService $mrp) {}

    public function index(Request $request): Response
    {
        $run = $request->query('run') ? MrpRun::find($request->query('run')) : MrpRun::latest('id')->first();

        return Inertia::render('Manufacturing/Mrp/Index', [
            'run' => $run,
            'suggestions' => $run ? $run->suggestions()->with('product:id,name_ar,sku')->orderBy('type')->orderBy('need_date')->get() : [],
            'runs' => MrpRun::latest('id')->limit(10)->get(['id', 'run_date', 'horizon_days']),
            'warehouseId' => $this->mrp->defaultWarehouseId(),
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate(['horizon_days' => 'required|integer|min:1|max:365']);
        $run = $this->mrp->run((int) $data['horizon_days']);

        return redirect()->route('manufacturing.mrp.index', ['run' => $run->id])->with('success', 'اكتمل تشغيل تخطيط الاحتياجات: '.$run->suggestions()->count().' اقتراحاً.');
    }

    public function convertPurchase(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $pr = $this->mrp->convertToPurchaseRequest($data['ids']);

        return redirect()->route('purchases.requests.show', $pr->id)->with('success', "أُنشئ طلب الشراء {$pr->request_number}");
    }

    public function convertProduction(Request $request, MrpSuggestion $suggestion)
    {
        $data = $request->validate(['warehouse_id' => 'required|tenant_exists:warehouses,id']);
        $order = $this->mrp->convertToProductionOrder($suggestion, (int) $data['warehouse_id']);

        return redirect()->route('manufacturing.orders.show', $order)->with('success', "أُنشئ أمر الإنتاج {$order->order_number}");
    }

    public function dismiss(MrpSuggestion $suggestion)
    {
        $suggestion->update(['status' => 'dismissed']);

        return back();
    }

    public function capacity(Request $request, CapacityPlanner $planner): Response
    {
        return Inertia::render('Manufacturing/Capacity/Index', ['rows' => $planner->load(8)]);
    }
}
