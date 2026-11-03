<?php

namespace Modules\Construction\Http\Controllers;

use App\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إضافة وحدة قياس سريعة من شاشات بنود المقاولات (عرض السعر، بنود المشروع، ملاحق العقد).
 * الوحدة بتتسجل في جدول وحدات النظام الأساسي للشركة، بنفس صلاحية شاشة الوحدات (unit.create).
 */
class UnitQuickCreateController extends BaseController
{
    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('construction.access');
        abort_unless($this->isAdmin() || auth()->user()->can('unit.create'), 403, __('construction::lang.unauthorized'));

        $businessId = $this->businessId();
        $request->merge([
            'actual_name' => trim((string) $request->input('actual_name')),
            'short_name' => trim((string) $request->input('short_name')),
        ]);
        $unique = fn (string $column) => Rule::unique('units', $column)
            ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'));

        $validated = $request->validate([
            'actual_name' => ['required', 'string', 'max:190', $unique('actual_name')],
            'short_name' => ['required', 'string', 'max:50', $unique('short_name')],
            'allow_decimal' => ['nullable', 'boolean'],
        ], [
            'actual_name.unique' => __('construction::lang.unit_name_exists'),
            'short_name.unique' => __('construction::lang.unit_short_name_exists'),
        ], [
            'actual_name' => __('construction::lang.unit_name'),
            'short_name' => __('construction::lang.unit_short_name'),
        ]);

        $unit = Unit::create([
            'business_id' => $businessId,
            'actual_name' => $validated['actual_name'],
            'short_name' => $validated['short_name'],
            'allow_decimal' => $request->boolean('allow_decimal') ? 1 : 0,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'id' => $unit->id,
            'label' => $unit->actual_name.' ('.$unit->short_name.')',
            'message' => __('construction::lang.unit_created'),
        ], 201);
    }
}
