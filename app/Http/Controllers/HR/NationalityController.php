<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Nationality;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The company's list of nationalities (employee nationality dropdown). */
class NationalityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive,all']);
        $status = $filters['status'] ?? 'active';
        $counts = Employee::query()->selectRaw('nationality, COUNT(*) as total')->groupBy('nationality')->pluck('total', 'nationality');

        $rows = Nationality::query()
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name_ar', 'like', "%{$s}%")->orWhere('name_en', 'like', "%{$s}%")->orWhere('code', 'like', strtoupper($s).'%')))
            ->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Nationality $n) => $n->toArray() + ['employees' => (int) ($counts[$n->code] ?? 0)]);

        return Inertia::render('HR/Nationalities/Index', [
            'nationalities' => $rows,
            'stats' => ['total' => Nationality::count(), 'active' => Nationality::where('is_active', true)->count(), 'inactive' => Nationality::where('is_active', false)->count()],
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('HR/Nationalities/Form', ['nationality' => null]);
    }

    public function edit(Nationality $nationality): Response
    {
        return Inertia::render('HR/Nationalities/Form', ['nationality' => $nationality]);
    }

    public function store(Request $request)
    {
        $nationality = Nationality::create($this->validated($request) + ['sort' => (int) Nationality::max('sort') + 1]);

        if ($json = $this->createdResponse($request, $nationality, $nationality->name_ar)) {
            return $json;
        }

        return redirect()->route('hr.nationalities.index')->with('success', 'تمت إضافة الجنسية');
    }

    public function update(Request $request, Nationality $nationality)
    {
        $nationality->update($this->validated($request, $nationality));

        return redirect()->route('hr.nationalities.index')->with('success', 'تم تحديث الجنسية');
    }

    protected function validated(Request $request, ?Nationality $nationality = null): array
    {
        $companyId = app(TenantManager::class)->getTenantId();
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('nationalities', 'code')->where('company_id', $companyId)->ignore($nationality?->id)],
            'name_ar' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
