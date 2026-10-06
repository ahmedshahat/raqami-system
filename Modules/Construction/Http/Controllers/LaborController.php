<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionProject;

class LaborController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizePermission('construction.labor.view');
        $businessId = $this->businessId();
        $projects = ConstructionProject::where('business_id', $businessId)->orderBy('name')->get(['id', 'code', 'name']);
        $selectedProjectId = $request->integer('project_id') ?: null;
        abort_if($selectedProjectId && ! $projects->contains('id', $selectedProjectId), 404);
        $status = in_array($request->input('status'), ['draft', 'approved', 'cancelled'], true) ? $request->input('status') : null;
        $search = trim((string) $request->input('q'));
        $base = ConstructionLaborSheet::where('business_id', $businessId);
        $stats = [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'approved_total' => (float) DB::table('construction_labor_sheet_lines as lines')->join('construction_labor_sheets as sheets', 'sheets.id', '=', 'lines.sheet_id')->where('sheets.business_id', $businessId)->where('sheets.status', 'approved')->sum('lines.total_cost'),
        ];
        $sheets = ConstructionLaborSheet::query()->where('business_id', $businessId)
            ->with('project:id,code,name')->withCount('lines')->withSum('lines', 'quantity')->withSum('lines', 'total_cost')
            ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($nested) use ($search) {
                $nested->where('number', 'like', "%{$search}%")->orWhere('work_site', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }))->latest('period_end')->latest('id')->paginate(20)->withQueryString();

        $canCancelApproved = $this->isAdmin();

        return view('construction::labor.index', compact('projects', 'selectedProjectId', 'status', 'search', 'stats', 'sheets', 'canCancelApproved'));
    }

    public function create()
    {
        $this->authorizePermission('construction.labor.manage');
        return view('construction::labor.create', [
            'projects' => ConstructionProject::where('business_id', $this->businessId())->whereNotIn('status', ['cancelled', 'completed'])->orderBy('name')->get(),
            'nextNumber' => $this->nextNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.labor.manage');
        $validated = $this->validatedSheet($request);
        $sheet = ConstructionLaborSheet::create($validated + ['business_id' => $this->businessId(), 'status' => 'draft', 'created_by' => auth()->id()]);
        return redirect()->route('construction.labor.show', $sheet->id)->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_sheet_created')]);
    }

    public function show(int $sheet)
    {
        $this->authorizePermission('construction.labor.view');
        $sheet = $this->findSheet($sheet);
        $sheet->load(['project:id,code,name,customer_id,manager_id', 'lines.worker:id,surname,first_name,last_name', 'lines.boqItem:id,code,description', 'approvedBy:id,surname,first_name,last_name', 'cancelledBy:id,surname,first_name,last_name']);
        $canCancelApproved = $this->isAdmin();
        return view('construction::labor.show', array_merge($this->lineOptions($sheet), compact('sheet', 'canCancelApproved')));
    }

    public function edit(int $sheet)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422, __('construction::lang.approved_labor_sheet_locked'));
        $sheet->load('project:id,code,name');
        return view('construction::labor.edit', compact('sheet'));
    }

    public function update(Request $request, int $sheet)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422, __('construction::lang.approved_labor_sheet_locked'));
        $sheet->update($this->validatedSheet($request, $sheet));
        return redirect()->route('construction.labor.show', $sheet->id)->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_sheet_updated')]);
    }

    public function destroy(int $sheet)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422, __('construction::lang.approved_labor_sheet_delete_blocked'));
        DB::transaction(function () use ($sheet) { $sheet->lines()->delete(); $sheet->delete(); });
        return redirect()->route('construction.labor.index')->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_sheet_deleted')]);
    }

    public function storeLine(Request $request, int $sheet)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422);
        $validated = $this->validatedLine($request, $sheet);
        $sheet->lines()->create($validated + ['business_id' => $this->businessId(), 'project_id' => $sheet->project_id]);
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_line_added')]);
    }

    public function updateLine(Request $request, int $sheet, int $line)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422);
        $line = $sheet->lines()->where('business_id', $this->businessId())->findOrFail($line);
        $line->update($this->validatedLine($request, $sheet));
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_line_updated')]);
    }

    public function destroyLine(int $sheet, int $line)
    {
        $this->authorizePermission('construction.labor.manage');
        $sheet = $this->findSheet($sheet);
        abort_unless($sheet->isEditable(), 422);
        $sheet->lines()->where('business_id', $this->businessId())->findOrFail($line)->delete();
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_line_deleted')]);
    }

    public function approve(int $sheet)
    {
        $this->authorizePermission('construction.labor.approve');
        DB::transaction(function () use ($sheet) {
            $sheet = ConstructionLaborSheet::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($sheet);
            abort_unless($sheet->isEditable(), 422);
            abort_if(! $sheet->lines()->exists(), 422, __('construction::lang.labor_sheet_empty'));
            $sheet->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });
        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_sheet_approved')]);
    }

    public function cancel(Request $request, int $sheet)
    {
        $this->authorizePermission('construction.labor.approve');
        abort_unless($this->isAdmin(), 403, __('construction::lang.only_manager_can_cancel_labor_sheet'));

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('construction::lang.invalid_manager_password'),
            ]);
        }

        DB::transaction(function () use ($sheet, $validated) {
            $sheet = ConstructionLaborSheet::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($sheet);
            abort_unless($sheet->status === 'approved', 422, __('construction::lang.only_approved_labor_sheet_can_be_cancelled'));
            $sheet->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
        });

        return redirect()->route('construction.labor.show', $sheet)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.labor_sheet_cancelled')]);
    }

    public function preview(int $sheet) { return view('construction::labor.print', $this->printData($sheet) + ['autoPrint' => false]); }
    public function print(int $sheet) { return view('construction::labor.print', $this->printData($sheet) + ['autoPrint' => true]); }

    private function validatedSheet(Request $request, ?ConstructionLaborSheet $sheet = null): array
    {
        $this->normalizeBusinessDates($request, ['period_start', 'period_end']);
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('construction_projects', 'id')->where(fn ($q) => $q->where('business_id', $this->businessId()))],
            'number' => ['required', 'string', 'max:60', Rule::unique('construction_labor_sheets', 'number')->ignore($sheet?->id)->where(fn ($q) => $q->where('business_id', $this->businessId()))],
            'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'work_site' => ['nullable', 'string', 'max:190'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($sheet) $validated['project_id'] = $sheet->project_id;
        return $validated;
    }

    private function validatedLine(Request $request, ConstructionLaborSheet $sheet): array
    {
        $this->normalizeLocalizedNumbers($request, ['quantity', 'unit_cost']);
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('business_id', $this->businessId()))],
            'worker_name' => ['nullable', 'string', 'max:190', 'required_without:user_id'],
            'role_name' => ['nullable', 'string', 'max:190'],
            'calculation_type' => ['required', Rule::in(['hour', 'day', 'linear_meter', 'square_meter', 'cubic_meter', 'unit', 'lump_sum'])],
            'quantity' => ['required', 'numeric', 'gt:0'], 'unit_cost' => ['required', 'numeric', 'min:0'],
            'boq_item_id' => ['nullable', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($q) => $q->where('business_id', $this->businessId())->where('project_id', $sheet->project_id)->where('row_type', 'item'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($validated['user_id'])) {
            $user = User::where('business_id', $this->businessId())->findOrFail($validated['user_id']);
            $validated['worker_name'] = trim(collect([$user->surname, $user->first_name, $user->last_name])->filter()->implode(' '));
        }
        if ($validated['calculation_type'] === 'lump_sum') $validated['quantity'] = 1;
        $validated['total_cost'] = round((float) $validated['quantity'] * (float) $validated['unit_cost'], 4);
        return $validated;
    }

    private function lineOptions(ConstructionLaborSheet $sheet): array
    {
        return [
            'workers' => User::forDropdown($this->businessId(), false),
            'boqItems' => ConstructionBoqItem::where('business_id', $this->businessId())->where('project_id', $sheet->project_id)->where('row_type', 'item')->orderBy('sort_order')->get(['id', 'code', 'description']),
        ];
    }

    private function findSheet(int $id): ConstructionLaborSheet { return ConstructionLaborSheet::where('business_id', $this->businessId())->findOrFail($id); }
    private function nextNumber(): string { return 'LAB-'.str_pad((string) ((int) ConstructionLaborSheet::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT); }

    private function printData(int $id): array
    {
        $this->authorizePermission('construction.labor.view');
        $sheet = $this->findSheet($id);
        $sheet->load(['project.customer:id,name,supplier_business_name', 'project.manager:id,surname,first_name,last_name', 'createdBy:id,surname,first_name,last_name', 'approvedBy:id,surname,first_name,last_name', 'cancelledBy:id,surname,first_name,last_name', 'lines.worker:id,surname,first_name,last_name', 'lines.boqItem:id,code,description']);
        return ['sheet' => $sheet, 'business' => Business::findOrFail($this->businessId()), 'businessLocation' => BusinessLocation::where('business_id', $this->businessId())->first()];
    }
}
