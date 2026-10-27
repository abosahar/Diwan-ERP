<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\Equipment;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionTimeLog;
use App\Models\RoutingOperation;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkCenterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Manufacturing/WorkCenters/Index', ['centers' => WorkCenter::with('equipment:id,code,name')->orderBy('code')->get()]);
    }

    public function create(): Response
    {
        return $this->form(new WorkCenter(['type' => 'machine', 'capacity_hours_per_day' => 8, 'efficiency' => 100, 'is_active' => true]));
    }

    public function edit(WorkCenter $workCenter): Response
    {
        return $this->form($workCenter);
    }

    protected function form(WorkCenter $center): Response
    {
        return Inertia::render('Manufacturing/WorkCenters/Form', [
            'center' => $center,
            'equipment' => Equipment::orderBy('code')->get(['id', 'code', 'name']),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_ar']),
        ]);
    }

    public function store(Request $request)
    {
        $center = WorkCenter::create($this->validated($request));

        if ($json = $this->createdResponse($request, $center, $center->name)) {
            return $json;
        }

        return redirect()->route('manufacturing.work-centers.index')->with('success', 'تمت إضافة مركز العمل.');
    }

    public function update(Request $request, WorkCenter $workCenter)
    {
        $workCenter->update($this->validated($request, $workCenter));

        return redirect()->route('manufacturing.work-centers.index')->with('success', 'تم تحديث مركز العمل.');
    }

    public function destroy(WorkCenter $workCenter)
    {
        $used = RoutingOperation::where('work_center_id', $workCenter->id)->exists()
            || ProductionOrderOperation::where('work_center_id', $workCenter->id)->exists()
            || ProductionTimeLog::where('work_center_id', $workCenter->id)->exists();
        if ($used) {
            $workCenter->update(['is_active' => false]);

            return back()->with('success', 'مركز العمل مستعمل في مسارات أو أوامر فأُوقف ولم يُحذف.');
        }
        $workCenter->delete();

        return back()->with('success', 'تم حذف مركز العمل.');
    }

    protected function validated(Request $request, ?WorkCenter $center = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('work_centers', 'code')->where('company_id', $request->user()->company_id)->ignore($center?->id)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:machine,line,labour,workshop',
            'equipment_id' => 'nullable|tenant_exists:equipment,id',
            'cost_center_id' => 'nullable|tenant_exists:cost_centers,id',
            'capacity_hours_per_day' => 'required|numeric|min:0.1|max:24',
            'efficiency' => 'required|numeric|min:1|max:200',
            'labour_rate_per_hour' => 'required|numeric|min:0',
            'overhead_rate_per_hour' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
