<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionCostCode;

class CostCodeController extends BaseController
{
    public function index()
    {
        $this->authorizePermission('construction.boq.view');
        $costCodes = ConstructionCostCode::query()
            ->where('business_id', $this->businessId())
            ->with('parent:id,code,name')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('construction::cost_codes.index', compact('costCodes'));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.boq.manage');
        $validated = $request->validate([
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('construction_cost_codes', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())),
            ],
            'code' => [
                'required', 'string', 'max:60',
                Rule::unique('construction_cost_codes', 'code')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())),
            ],
            'name' => ['required', 'string', 'max:190'],
            'category' => ['required', Rule::in(ConstructionCostCode::CATEGORIES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $costCode = ConstructionCostCode::create([
            'business_id' => $this->businessId(),
            'parent_id' => $validated['parent_id'] ?? null,
            'code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $costCode->id, 'message' => __('construction::lang.cost_code_created')], 201);
        }

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.cost_code_created')]);
    }
}
