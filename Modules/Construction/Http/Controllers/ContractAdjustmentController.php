<?php

namespace Modules\Construction\Http\Controllers;

use App\Unit;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionContract;
use Modules\Construction\Entities\ConstructionContractAdjustment;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\AuditTrail;

class ContractAdjustmentController extends BaseController
{
    public function index(int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.view');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        abort_unless($contract->status === 'active', 422, __('construction::lang.adjustments_require_active_contract'));
        $contract->load(['boqVersion.items' => fn ($query) => $query->where('row_type', 'item')->where('item_kind', '!=', 'variation')->orderBy('sort_order')]);
        $adjustments = $contract->adjustments()->with(['originalItem', 'boqItem', 'createdBy', 'approvedBy'])->latest('id')->get();
        $units = Unit::where('business_id', $this->businessId())->whereNull('deleted_at')->orderBy('actual_name')->get(['id', 'actual_name', 'short_name']);

        return view('construction::adjustments.index', compact('project', 'contract', 'adjustments', 'units'));
    }

    public function store(Request $request, int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        abort_unless($contract->status === 'active', 422, __('construction::lang.adjustments_require_active_contract'));
        $validated = $this->validated($request, $contract);

        $adjustment = DB::transaction(function () use ($project, $contract, $validated) {
            $unit = Unit::where('business_id', $this->businessId())->findOrFail($validated['unit_id']);
            $adjustment = $contract->adjustments()->create([
                'business_id' => $this->businessId(),
                'project_id' => $project->id,
                'original_boq_item_id' => $validated['original_boq_item_id'] ?? null,
                'code' => $validated['code'] ?: null,
                'description' => trim($validated['description']),
                'unit_id' => $unit->id,
                'unit' => $unit->short_name,
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round((float) $validated['quantity'] * (float) $validated['unit_price'], 4),
                'reason' => trim($validated['reason']),
                'adjustment_date' => $validated['adjustment_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            AuditTrail::record('contract_adjustment_created', $adjustment, $project->id, [], $adjustment->only(['original_boq_item_id', 'description', 'unit_id', 'quantity', 'unit_price', 'total', 'reason', 'adjustment_date', 'status']));

            return $adjustment;
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.adjustment_saved')]);
    }

    public function update(Request $request, int $project, int $contract, int $adjustment)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        $adjustment = $contract->adjustments()->where('business_id', $this->businessId())->findOrFail($adjustment);
        abort_unless($adjustment->status === 'draft', 422, __('construction::lang.approved_adjustment_is_locked'));
        $validated = $this->validated($request, $contract);
        $old = $adjustment->only(['original_boq_item_id', 'code', 'description', 'unit_id', 'quantity', 'unit_price', 'total', 'reason', 'adjustment_date', 'notes']);
        $unit = Unit::where('business_id', $this->businessId())->findOrFail($validated['unit_id']);
        $adjustment->update([
            'original_boq_item_id' => $validated['original_boq_item_id'] ?? null,
            'code' => $validated['code'] ?: null,
            'description' => trim($validated['description']),
            'unit_id' => $unit->id,
            'unit' => $unit->short_name,
            'quantity' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'total' => round((float) $validated['quantity'] * (float) $validated['unit_price'], 4),
            'reason' => trim($validated['reason']),
            'adjustment_date' => $validated['adjustment_date'],
            'notes' => $validated['notes'] ?? null,
        ]);
        AuditTrail::record('contract_adjustment_updated', $adjustment, $project->id, $old, $adjustment->only(array_keys($old)));

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.adjustment_saved')]);
    }

    public function destroy(int $project, int $contract, int $adjustment)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        $adjustment = $contract->adjustments()->where('business_id', $this->businessId())->findOrFail($adjustment);
        abort_unless($adjustment->status === 'draft', 422, __('construction::lang.approved_adjustment_is_locked'));
        $old = $adjustment->toArray();
        AuditTrail::record('contract_adjustment_deleted', $adjustment, $project->id, $old, []);
        $adjustment->delete();

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.adjustment_deleted')]);
    }

    public function approve(int $project, int $contract, int $adjustment)
    {
        $this->authorizePermission('construction.contract.approve');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);

        DB::transaction(function () use ($project, $contract, $adjustment) {
            $adjustment = ConstructionContractAdjustment::where('business_id', $this->businessId())->where('contract_id', $contract->id)->whereKey($adjustment)->lockForUpdate()->firstOrFail();
            abort_unless($contract->status === 'active' && $adjustment->status === 'draft', 422, __('construction::lang.approved_adjustment_is_locked'));
            $boq = $contract->boqVersion()->lockForUpdate()->firstOrFail();
            $source = $adjustment->original_boq_item_id ? $boq->items()->where('row_type', 'item')->findOrFail($adjustment->original_boq_item_id) : null;
            $codeBase = trim((string) ($adjustment->code ?: ($source ? $source->code.'-V' : 'VAR-'.$adjustment->id)));
            $code = $codeBase;
            $suffix = 1;
            while ($boq->items()->where('code', $code)->exists()) {
                $code = $codeBase.'-'.(++$suffix);
            }
            $item = $boq->items()->create([
                'business_id' => $this->businessId(), 'project_id' => $project->id,
                'row_type' => 'item', 'code' => $code, 'description' => $adjustment->description,
                'unit_id' => $adjustment->unit_id, 'unit' => $adjustment->unit,
                'contract_quantity' => $adjustment->quantity, 'sales_unit_price' => $adjustment->unit_price,
                'sales_total' => $adjustment->total, 'item_kind' => 'variation',
                'sort_order' => ((int) $boq->items()->max('sort_order')) + 1,
                'notes' => $adjustment->reason.($adjustment->notes ? "\n".$adjustment->notes : ''),
            ]);
            $adjustment->update(['boq_item_id' => $item->id, 'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            AuditTrail::record('contract_adjustment_approved', $adjustment, $project->id, ['status' => 'draft'], ['status' => 'approved', 'boq_item_id' => $item->id]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.adjustment_approved')]);
    }

    private function validated(Request $request, ConstructionContract $contract): array
    {
        $util = new Util();
        $request->merge([
            'quantity' => $util->num_uf($request->input('quantity', 0)),
            'unit_price' => $util->num_uf($request->input('unit_price', 0)),
        ]);
        $this->normalizeBusinessDates($request, ['adjustment_date']);

        return $request->validate([
            'original_boq_item_id' => ['nullable', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('boq_version_id', $contract->boq_version_id)->where('row_type', 'item')->where('item_kind', '!=', 'variation'))],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:500'],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'adjustment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findContract(ConstructionProject $project, int $id): ConstructionContract
    {
        return $project->contracts()->where('business_id', $this->businessId())->findOrFail($id);
    }
}
