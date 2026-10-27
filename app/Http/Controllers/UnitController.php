<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Units of measure: created on the fly from the product form's unit dropdown. */
class UnitController extends Controller
{
    public function store(Request $request)
    {
        $companyId = app(TenantManager::class)->getTenantId() ?? $request->user()?->company_id;
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('units', 'name_ar')->where('company_id', $companyId)],
            'name_en' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:20',
        ]);

        $unit = Unit::create($validated + ['company_id' => $companyId]);

        if ($json = $this->createdResponse($request, $unit, $unit->name_ar)) {
            return $json;
        }

        return back()->with('success', 'تمت إضافة الوحدة');
    }
}
