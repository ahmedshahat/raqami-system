<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Unit;
use Modules\Construction\Entities\ConstructionBoqCostAllocation;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionBoqVersion;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\AuditTrail;

class BoqController extends BaseController
{
    public function workspaceIndex(Request $request)
    {
        $this->authorizePermission('construction.boq.view');

        $businessId = $this->businessId();
        $projects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->with([
                'customer:id,name,supplier_business_name',
                'approvedBoq',
            ])
            ->latest('id')
            ->paginate(20);

        return view('construction::boq.workspace', compact('projects'));
    }

    public function index(int $project)
    {
        $this->authorizePermission('construction.boq.view');
        $project = $this->findProject($project);
        $version = $project->boqVersions()->latest('version_number')->first();

        if (! $version) {
            $this->authorizePermission('construction.boq.manage');
            $version = $this->createVersion($project, __('construction::lang.boq_default_name'));
        }

        return redirect()->route('construction.projects.boq.show', [$project->id, $version->id]);
    }

    public function storeVersion(Request $request, int $project)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $version = $this->createVersion($project, trim($validated['name']), $validated['notes'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['id' => $version->id, 'url' => route('construction.projects.boq.show', [$project->id, $version->id]), 'message' => __('construction::lang.boq_version_created')], 201);
        }

        return redirect()->route('construction.projects.boq.show', [$project->id, $version->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.boq_version_created')]);
    }

    public function show(int $project, int $version)
    {
        $this->authorizePermission('construction.boq.view');
        $project = $this->findProject($project)->load('primaryContract');
        $version = $this->findVersion($project, $version);
        $version->load([
            'approvedBy:id,surname,first_name,last_name,username',
            'items' => fn ($query) => $query
                ->with('allocations.costCode')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);
        $versions = $project->boqVersions()
            ->withCount(['items' => fn ($query) => $query->where('row_type', 'item')])
            ->latest('version_number')
            ->get();

        $units = Unit::where('business_id', $this->businessId())->orderBy('actual_name')->get(['id', 'actual_name', 'short_name']);

        $canEditItems = $this->canEditItems($project, $version);

        return view('construction::boq.show', compact('project', 'version', 'versions', 'units', 'canEditItems'));
    }

    public function storeItem(Request $request, int $project, int $version)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);

        $rows = $request->input('items');
        if (! is_array($rows)) {
            $rows = [$request->only([
                'parent_id', 'row_type', 'code', 'description', 'unit_id',
                'contract_quantity', 'sales_unit_price', 'item_kind', 'sort_order', 'notes',
            ])];
        }
        $rows = array_values(array_map(function ($row) {
            $row['row_type'] = $row['row_type'] ?? 'item';
            $row['item_kind'] = $row['item_kind'] ?? 'standard';
            $util = new \App\Utils\Util();
            $row['contract_quantity'] = $util->num_uf($row['contract_quantity'] ?? 0);
            $row['sales_unit_price'] = $util->num_uf($row['sales_unit_price'] ?? 0);
            return $row;
        }, $rows));
        $request->merge(['items' => $rows]);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.parent_id' => [
                'nullable', 'integer',
                Rule::exists('construction_boq_items', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())
                    ->where('boq_version_id', $version->id)
                    ->where('row_type', 'section')),
            ],
            'items.*.row_type' => ['required', Rule::in(ConstructionBoqItem::ROW_TYPES)],
            'items.*.code' => [
                'nullable', 'string', 'max:60', 'distinct',
                Rule::unique('construction_boq_items', 'code')->where(fn ($query) => $query
                    ->where('boq_version_id', $version->id)),
            ],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at'))],
            'items.*.contract_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.sales_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_kind' => ['required', Rule::in(ConstructionBoqItem::ITEM_KINDS)],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'items.*.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $unitIds = collect($validated['items'])->pluck('unit_id')->filter()->unique();
        $units = Unit::where('business_id', $this->businessId())->whereIn('id', $unitIds)->get()->keyBy('id');
        foreach ($validated['items'] as $row) {
            abort_if($row['row_type'] === 'item' && empty($row['unit_id']), 422, __('construction::lang.boq_unit_required'));
        }

        DB::transaction(function () use ($validated, $project, $version, $units) {
            $nextItemNumber = $version->items()->where('row_type', 'item')->count() + 1;
            foreach ($validated['items'] as $row) {
                $isSection = $row['row_type'] === 'section';
                $quantity = $isSection ? 0 : (float) ($row['contract_quantity'] ?? 0);
                $unitPrice = $isSection ? 0 : (float) ($row['sales_unit_price'] ?? 0);
                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') {
                    do {
                        $code = 'ITM-'.str_pad((string) $nextItemNumber++, 3, '0', STR_PAD_LEFT);
                    } while ($version->items()->where('code', $code)->exists());
                }

                $item = $version->items()->create([
                    'business_id' => $this->businessId(),
                    'project_id' => $project->id,
                    'parent_id' => $row['parent_id'] ?? null,
                    'row_type' => $row['row_type'],
                    'code' => $code,
                    'description' => trim($row['description']),
                    'unit_id' => $isSection ? null : $row['unit_id'],
                    'unit' => $isSection ? null : $units[$row['unit_id']]->short_name,
                    'contract_quantity' => $quantity,
                    'sales_unit_price' => $unitPrice,
                    'sales_total' => round($quantity * $unitPrice, 4),
                    'item_kind' => $isSection ? 'standard' : $row['item_kind'],
                    'sort_order' => $row['sort_order'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                ]);
                AuditTrail::record('project_item_created', $item, $project->id, [], $item->only(['code', 'description', 'unit_id', 'contract_quantity', 'sales_unit_price', 'sales_total', 'notes']));
            }
            $version->refreshTotals();
        });

        return back()->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.boq_items_created', ['count' => count($validated['items'])]),
        ]);
    }

    public function updateItem(Request $request, int $project, int $version, int $item)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $item = $version->items()->where('business_id', $this->businessId())->where('row_type', 'item')->findOrFail($item);
        $this->normalizeLocalizedNumbers($request, ['contract_quantity', 'sales_unit_price']);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('construction_boq_items', 'code')->where(fn ($query) => $query->where('boq_version_id', $version->id))->ignore($item->id)],
            'description' => ['required', 'string', 'max:500'],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at'))],
            'contract_quantity' => ['required', 'numeric', 'gt:0'],
            'sales_unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $unit = Unit::where('business_id', $this->businessId())->findOrFail($validated['unit_id']);
        $old = $item->only(['code', 'description', 'unit_id', 'unit', 'contract_quantity', 'sales_unit_price', 'sales_total', 'notes']);
        $item->update([
            'code' => trim($validated['code']), 'description' => trim($validated['description']),
            'unit_id' => $unit->id, 'unit' => $unit->short_name,
            'contract_quantity' => $validated['contract_quantity'], 'sales_unit_price' => $validated['sales_unit_price'],
            'sales_total' => round((float) $validated['contract_quantity'] * (float) $validated['sales_unit_price'], 4),
            'notes' => $validated['notes'] ?? null,
        ]);
        $version->refreshTotals();
        AuditTrail::record('project_item_updated', $item, $project->id, $old, $item->only(array_keys($old)));

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.boq_item_updated')]);
    }

    public function destroyItem(int $project, int $version, int $item)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $item = $version->items()->where('business_id', $this->businessId())->findOrFail($item);
        abort_if($item->children()->exists(), 422, __('construction::lang.boq_item_has_children'));

        DB::transaction(function () use ($item, $version) {
            AuditTrail::record('project_item_deleted', $item, $item->project_id, $item->only(['code', 'description', 'unit_id', 'contract_quantity', 'sales_unit_price', 'sales_total', 'notes']), []);
            $item->allocations()->delete();
            $item->delete();
            $version->refreshTotals();
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.boq_item_deleted')]);
    }

    public function storeAllocation(Request $request, int $project, int $version, int $item)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $item = $version->items()->where('row_type', 'item')->findOrFail($item);
        $validated = $request->validate([
            'cost_code_id' => [
                'required', 'integer',
                Rule::exists('construction_cost_codes', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())
                    ->where('is_active', true)),
            ],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        ConstructionBoqCostAllocation::updateOrCreate(
            ['boq_item_id' => $item->id, 'cost_code_id' => $validated['cost_code_id']],
            [
                'business_id' => $this->businessId(),
                'project_id' => $project->id,
                'boq_version_id' => $version->id,
                'estimated_cost' => $validated['estimated_cost'],
                'notes' => $validated['notes'] ?? null,
            ]
        );
        $version->refreshTotals();

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.cost_allocation_saved')]);
    }

    public function destroyAllocation(int $project, int $version, int $item, int $allocation)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $item = $version->items()->findOrFail($item);
        $allocation = $item->allocations()->where('business_id', $this->businessId())->findOrFail($allocation);
        $allocation->delete();
        $version->refreshTotals();

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.cost_allocation_deleted')]);
    }

    public function approve(int $project, int $version)
    {
        $this->authorizePermission('construction.boq.approve');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        abort_unless($version->status === 'draft', 422, __('construction::lang.approved_boq_is_locked'));
        abort_unless($version->items()->where('row_type', 'item')->exists(), 422, __('construction::lang.cannot_approve_empty_boq'));

        DB::transaction(function () use ($project, $version) {
            $version->refreshTotals();
            $project->boqVersions()->where('status', 'approved')->update(['status' => 'superseded']);
            $version->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            AuditTrail::record('project_items_approved', $version, $project->id, ['status' => 'draft'], ['status' => 'approved', 'sales_total' => $version->sales_total]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.boq_approved')]);
    }

    public function revise(int $project, int $version)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $source = $this->findVersion($project, $version);
        abort_if($source->status === 'draft', 422, __('construction::lang.draft_boq_cannot_be_revised'));

        $newVersion = DB::transaction(function () use ($project, $source) {
            $source->load('items.allocations');
            $copy = $this->createVersion(
                $project,
                __('construction::lang.boq_revision_name', ['number' => $source->version_number + 1]),
                __('construction::lang.boq_revision_note', ['number' => $source->version_number])
            );
            $itemMap = [];

            foreach ($source->items->sortBy('id') as $item) {
                $newItem = $copy->items()->create([
                    'business_id' => $this->businessId(),
                    'project_id' => $project->id,
                    'parent_id' => $item->parent_id ? ($itemMap[$item->parent_id] ?? null) : null,
                    'row_type' => $item->row_type,
                    'code' => $item->code,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'unit_id' => $item->unit_id,
                    'contract_quantity' => $item->contract_quantity,
                    'sales_unit_price' => $item->sales_unit_price,
                    'sales_total' => $item->sales_total,
                    'item_kind' => $item->item_kind,
                    'sort_order' => $item->sort_order,
                    'notes' => $item->notes,
                ]);
                $itemMap[$item->id] = $newItem->id;

                foreach ($item->allocations as $allocation) {
                    $newItem->allocations()->create([
                        'business_id' => $this->businessId(),
                        'project_id' => $project->id,
                        'boq_version_id' => $copy->id,
                        'cost_code_id' => $allocation->cost_code_id,
                        'estimated_cost' => $allocation->estimated_cost,
                        'notes' => $allocation->notes,
                    ]);
                }
            }

            $copy->refreshTotals();
            return $copy;
        });

        return redirect()->route('construction.projects.boq.show', [$project->id, $newVersion->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.boq_revision_created')]);
    }

    private function createVersion(ConstructionProject $project, string $name, ?string $notes = null): ConstructionBoqVersion
    {
        return DB::transaction(function () use ($project, $name, $notes) {
            $nextNumber = (int) ConstructionBoqVersion::query()
                ->where('business_id', $this->businessId())
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->max('version_number') + 1;

            return $project->boqVersions()->create([
                'business_id' => $this->businessId(),
                'version_number' => $nextNumber,
                'name' => $name,
                'notes' => $notes,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
        });
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findVersion(ConstructionProject $project, int $id): ConstructionBoqVersion
    {
        return $project->boqVersions()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function canEditItems(ConstructionProject $project, ConstructionBoqVersion $version): bool
    {
        return $version->status !== 'superseded'
            && ! $project->contracts()->whereIn('status', ['active', 'completed'])->exists()
            && ! $project->measurements()->where('status', 'approved')->exists()
            && ! $project->customerCertificates()->whereIn('status', ['approved', 'partially_approved', 'posted', 'paid'])->exists();
    }

    private function ensureEditable(ConstructionProject $project, ConstructionBoqVersion $version): void
    {
        abort_unless($this->canEditItems($project, $version), 422, __('construction::lang.contract_items_are_locked'));
    }
}
