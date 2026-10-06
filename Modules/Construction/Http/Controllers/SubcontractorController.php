<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;

class SubcontractorController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizePermission('construction.subcontract.view');
        $businessId = $this->businessId();
        $projects = ConstructionProject::where('business_id', $businessId)->orderBy('name')->get(['id', 'code', 'name']);
        $suppliers = $this->suppliers();
        $selectedProjectId = $request->integer('project_id') ?: null;
        $status = in_array($request->input('status'), ['draft', 'approved', 'completed', 'cancelled'], true) ? $request->input('status') : null;
        $search = trim((string) $request->input('q'));
        abort_if($selectedProjectId && ! $projects->contains('id', $selectedProjectId), 404);

        $base = ConstructionSubcontract::where('business_id', $businessId);
        $stats = [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'approved_total' => (float) (clone $base)->where('status', 'approved')->sum('total_value'),
        ];
        $subcontracts = ConstructionSubcontract::where('business_id', $businessId)
            ->with(['project:id,code,name', 'subcontractor:id,name,supplier_business_name'])
            ->withCount('items')
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('subcontractor', fn ($contact) => $contact->where('name', 'like', "%{$search}%")->orWhere('supplier_business_name', 'like', "%{$search}%"));
            }))->latest('id')->paginate(20)->withQueryString();

        return view('construction::subcontractors.index', compact('projects', 'suppliers', 'selectedProjectId', 'status', 'search', 'stats', 'subcontracts') + ['nextNumber' => $this->nextNumber()]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $validated = $this->validatedContract($request);
        $subcontract = ConstructionSubcontract::create($validated + [
            'business_id' => $this->businessId(),
            'number' => $this->nextNumber(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('construction.subcontractors.show', $subcontract->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_created')]);
    }

    public function show(int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.view');
        $subcontract = $this->findContract($subcontract);
        $subcontract->load(['project:id,code,name', 'subcontractor:id,name,supplier_business_name,mobile,address_line_1,address_line_2,city,state,country', 'items.boqItem:id,code,description,sales_total', 'certificates.items', 'createdBy:id,surname,first_name,last_name', 'approvedBy:id,surname,first_name,last_name']);

        return view('construction::subcontractors.show', compact('subcontract') + $this->contractOptions($subcontract));
    }

    public function update(Request $request, int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $subcontract = $this->findContract($subcontract);
        abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
        $subcontract->update($this->validatedContract($request, $subcontract));
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_updated')]);
    }

    public function destroy(int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $subcontract = $this->findContract($subcontract);
        abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
        DB::transaction(function () use ($subcontract) { $subcontract->items()->delete(); $subcontract->delete(); });
        return redirect()->route('construction.subcontractors.index')->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_deleted')]);
    }

    public function storeItem(Request $request, int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $subcontract = $this->findContract($subcontract);
        abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
        $data = $this->validatedItem($request, $subcontract);
        $subcontract->items()->create($data + ['business_id' => $this->businessId(), 'project_id' => $subcontract->project_id, 'sort_order' => ((int) $subcontract->items()->max('sort_order')) + 1]);
        $this->syncTotal($subcontract);
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_item_added')]);
    }

    public function updateItem(Request $request, int $subcontract, int $item)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $subcontract = $this->findContract($subcontract);
        abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
        $item = $subcontract->items()->where('business_id', $this->businessId())->findOrFail($item);
        $item->update($this->validatedItem($request, $subcontract));
        $this->syncTotal($subcontract);
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_item_updated')]);
    }

    public function destroyItem(int $subcontract, int $item)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $subcontract = $this->findContract($subcontract);
        abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
        $subcontract->items()->where('business_id', $this->businessId())->findOrFail($item)->delete();
        $this->syncTotal($subcontract);
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_item_deleted')]);
    }

    public function approve(int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.approve');
        DB::transaction(function () use ($subcontract) {
            $subcontract = ConstructionSubcontract::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($subcontract);
            abort_unless($subcontract->isEditable(), 422, __('construction::lang.approved_subcontract_locked'));
            abort_if(! $subcontract->items()->exists(), 422, __('construction::lang.subcontract_requires_items'));
            $this->syncTotal($subcontract);
            $subcontract->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_approved')]);
    }

    public function preview(int $subcontract) { return view('construction::subcontractors.print', $this->printData($subcontract) + ['autoPrint' => false]); }
    public function print(int $subcontract) { return view('construction::subcontractors.print', $this->printData($subcontract) + ['autoPrint' => true]); }

    private function validatedContract(Request $request, ?ConstructionSubcontract $contract = null): array
    {
        $this->normalizeBusinessDates($request, ['start_date', 'end_date']);
        $this->normalizeLocalizedNumbers($request, ['retention_percent']);
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('construction_projects', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId()))],
            'subcontractor_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereIn('type', ['supplier', 'both'])->whereNull('deleted_at'))],
            'title' => ['required', 'string', 'max:190'],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'retention_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($contract) $validated['project_id'] = $contract->project_id;
        return $validated;
    }

    private function validatedItem(Request $request, ConstructionSubcontract $subcontract): array
    {
        $this->normalizeLocalizedNumbers($request, ['quantity', 'unit_rate']);
        $validated = $request->validate([
            'boq_item_id' => ['nullable', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('project_id', $subcontract->project_id)->where('row_type', 'item'))],
            'description' => ['nullable', 'string', 'max:500', 'required_without:boq_item_id'],
            'calculation_type' => ['required', Rule::in(['linear_meter', 'square_meter', 'cubic_meter', 'unit', 'percentage', 'lump_sum'])],
            'quantity' => ['required', 'numeric', 'gt:0'], 'unit_rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $boqItem = ! empty($validated['boq_item_id']) ? ConstructionBoqItem::where('business_id', $this->businessId())->where('project_id', $subcontract->project_id)->where('row_type', 'item')->findOrFail($validated['boq_item_id']) : null;
        $validated['description'] = $validated['description'] ?: $boqItem?->description;
        if ($validated['calculation_type'] === 'percentage') {
            abort_unless($boqItem, 422, __('construction::lang.percentage_requires_project_item'));
            abort_if((float) $validated['quantity'] > 100, 422, __('construction::lang.percentage_cannot_exceed_100'));
            $validated['unit_rate'] = (float) $boqItem->sales_total;
            $validated['total_value'] = round((float) $validated['unit_rate'] * (float) $validated['quantity'] / 100, 4);
        } else {
            if ($validated['calculation_type'] === 'lump_sum') $validated['quantity'] = 1;
            $validated['total_value'] = round((float) $validated['quantity'] * (float) $validated['unit_rate'], 4);
        }
        return $validated;
    }

    private function contractOptions(ConstructionSubcontract $subcontract): array
    {
        $versionId = $subcontract->project->approvedBoq()->value('id') ?: $subcontract->project->boqVersions()->latest('version_number')->value('id');
        return [
            'suppliers' => $this->suppliers(),
            'boqItems' => ConstructionBoqItem::where('business_id', $this->businessId())->where('project_id', $subcontract->project_id)->where('boq_version_id', $versionId)->where('row_type', 'item')->orderBy('sort_order')->get(['id', 'code', 'description', 'sales_total']),
        ];
    }

    private function suppliers() { return Contact::where('business_id', $this->businessId())->whereIn('type', ['supplier', 'both'])->where('contact_status', 'active')->orderByRaw('COALESCE(supplier_business_name, name)')->get(['id', 'name', 'supplier_business_name']); }
    private function findContract(int $id): ConstructionSubcontract { return ConstructionSubcontract::where('business_id', $this->businessId())->findOrFail($id); }
    private function nextNumber(): string { return 'SUB-'.str_pad((string) ((int) ConstructionSubcontract::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT); }
    private function syncTotal(ConstructionSubcontract $subcontract): void { $subcontract->update(['total_value' => round((float) $subcontract->items()->sum('total_value'), 4)]); }

    private function printData(int $id): array
    {
        $this->authorizePermission('construction.subcontract.view');
        $subcontract = $this->findContract($id);
        $subcontract->load(['project:id,code,name', 'subcontractor:id,name,supplier_business_name,mobile,address_line_1,address_line_2,city,state,country', 'items.boqItem:id,code,description', 'createdBy:id,surname,first_name,last_name', 'approvedBy:id,surname,first_name,last_name']);
        return ['subcontract' => $subcontract, 'business' => Business::findOrFail($this->businessId()), 'businessLocation' => BusinessLocation::where('business_id', $this->businessId())->first()];
    }
}
