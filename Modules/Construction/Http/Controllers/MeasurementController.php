<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionMeasurement;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\AuditTrail;

class MeasurementController extends BaseController
{
    public function workspaceIndex(Request $request)
    {
        $this->authorizePermission('construction.measurement.view');

        $businessId = $this->businessId();
        $projects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->with([
                'customer:id,name,supplier_business_name',
            ])
            ->withCount('measurements')
            ->latest('id')
            ->paginate(20);

        return view('construction::measurements.workspace', compact('projects'));
    }

    public function index(int $project)
    {
        $this->authorizePermission('construction.measurement.view');
        $project = $this->findProject($project);
        $measurements = $project->measurements()->withCount('items')->latest('measurement_date')->latest('id')->get();
        $nextNumber = 'MSR-'.str_pad((string) ((int) $project->measurements()->max('id') + 1), 4, '0', STR_PAD_LEFT);

        return view('construction::measurements.index', compact('project', 'measurements', 'nextNumber'));
    }

    public function store(Request $request, int $project)
    {
        $this->authorizePermission('construction.measurement.manage');
        $project = $this->findProject($project);
        $this->contractBoq($project);
        $this->normalizeBusinessDates($request, ['measurement_date', 'period_from', 'period_to']);
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:60', Rule::unique('construction_measurements', 'number')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('project_id', $project->id))],
            'measurement_date' => ['required', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $measurement = $project->measurements()->create($validated + [
            'business_id' => $this->businessId(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $measurement->id, 'url' => route('construction.projects.measurements.show', [$project->id, $measurement->id]), 'message' => __('construction::lang.measurement_created')], 201);
        }

        return redirect()->route('construction.projects.measurements.show', [$project->id, $measurement->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.measurement_created')]);
    }

    public function show(int $project, int $measurement)
    {
        $this->authorizePermission('construction.measurement.view');
        $project = $this->findProject($project);
        $measurement = $this->findMeasurement($project, $measurement);
        $measurement->load(['items.boqItem', 'approvedBy:id,surname,first_name,last_name,username']);
        $approvedBoq = $this->contractBoq($project, false);
        $approvedBoq?->load(['items' => fn ($query) => $query->where('row_type', 'item')->orderBy('sort_order')]);

        $savedItems = $measurement->items->keyBy('boq_item_id');
        $previousApproved = DB::table('construction_measurement_items as lines')
            ->join('construction_measurements as headers', 'headers.id', '=', 'lines.measurement_id')
            ->where('lines.business_id', $this->businessId())
            ->where('lines.project_id', $project->id)
            ->where('headers.status', 'approved')
            ->where('headers.id', '<>', $measurement->id)
            ->selectRaw('lines.boq_item_id, SUM(lines.approved_quantity) as total')
            ->groupBy('lines.boq_item_id')
            ->pluck('total', 'lines.boq_item_id');

        $measurementRows = collect();
        if ($approvedBoq) {
            $measurementRows = $approvedBoq->items->map(function ($boqItem) use ($savedItems, $previousApproved) {
                $saved = $savedItems->get($boqItem->id);
                $previousQuantity = (float) ($previousApproved[$boqItem->id] ?? 0);
                $currentQuantity = (float) optional($saved)->executed_quantity;
                $contractQuantity = (float) $boqItem->contract_quantity;
                $unitPrice = (float) $boqItem->sales_unit_price;

                return (object) [
                    'boq_item' => $boqItem,
                    'measurement_item' => $saved,
                    'previous_quantity' => $previousQuantity,
                    'current_quantity' => $currentQuantity,
                    'total_quantity' => $previousQuantity + $currentQuantity,
                    'remaining_quantity' => max(0, $contractQuantity - $previousQuantity - $currentQuantity),
                    'current_value' => $currentQuantity * $unitPrice,
                    'previous_value' => $previousQuantity * $unitPrice,
                ];
            });
        }

        $measurementSummary = [
            'items_count' => $measurementRows->count(),
            'measured_items_count' => $measurementRows->filter(fn ($row) => $row->current_quantity > 0)->count(),
            'current_value' => $measurementRows->sum('current_value'),
            'previous_value' => $measurementRows->sum('previous_value'),
            'cumulative_value' => $measurementRows->sum(fn ($row) => $row->current_value + $row->previous_value),
        ];
        $linkedCertificateId = $measurement->items->pluck('certificate_id')->filter()->first();

        return view('construction::measurements.show', compact('project', 'measurement', 'approvedBoq', 'measurementRows', 'measurementSummary', 'linkedCertificateId'));
    }

    public function storeItem(Request $request, int $project, int $measurement)
    {
        $this->authorizePermission('construction.measurement.manage');
        $project = $this->findProject($project);
        $measurement = $this->findMeasurement($project, $measurement);
        $this->ensureEditable($measurement);
        $approvedBoq = $this->contractBoq($project);

        if ($request->has('items')) {
            return $this->storeItems($request, $project, $measurement, $approvedBoq);
        }

        $this->normalizeLocalizedNumbers($request, ['executed_quantity']);

        $validated = $request->validate([
            'boq_item_id' => ['required', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('project_id', $project->id)->where('boq_version_id', $approvedBoq->id)->where('row_type', 'item'))],
            'executed_quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousApproved = $this->previousApprovedQuantities($project, $measurement);
        $boqItem = $approvedBoq->items()->where('row_type', 'item')->findOrFail($validated['boq_item_id']);
        $this->validateContractLimit($boqItem, (float) ($previousApproved[$boqItem->id] ?? 0), (float) $validated['executed_quantity'], 'executed_quantity');

        $measurement->items()->updateOrCreate(
            ['boq_item_id' => $validated['boq_item_id'], 'structure_id' => null],
            [
                'business_id' => $this->businessId(),
                'project_id' => $project->id,
                'executed_quantity' => $validated['executed_quantity'],
                'approved_quantity' => 0,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'msg' => __('construction::lang.measurement_item_saved')]);
        }

        return redirect()->route('construction.projects.measurements.show', [$project->id, $measurement->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.measurement_item_saved')]);
    }

    private function storeItems(Request $request, ConstructionProject $project, ConstructionMeasurement $measurement, $approvedBoq)
    {
        $util = new Util();
        $rows = collect($request->input('items', []))->map(function ($row) use ($util) {
            $row['executed_quantity'] = ($row['executed_quantity'] ?? '') === ''
                ? 0
                : $util->num_uf($row['executed_quantity']);

            return $row;
        })->values()->all();
        $request->merge(['items' => $rows]);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.boq_item_id' => ['required', 'integer', 'distinct', Rule::exists('construction_boq_items', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('project_id', $project->id)->where('boq_version_id', $approvedBoq->id)->where('row_type', 'item'))],
            'items.*.executed_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $boqItems = $approvedBoq->items()->where('row_type', 'item')->get()->keyBy('id');
        $previousApproved = $this->previousApprovedQuantities($project, $measurement);

        DB::transaction(function () use ($validated, $measurement, $project, $boqItems, $previousApproved) {
            foreach ($validated['items'] as $index => $row) {
                $boqItem = $boqItems->get((int) $row['boq_item_id']);
                $currentQuantity = (float) ($row['executed_quantity'] ?? 0);
                $this->validateContractLimit($boqItem, (float) ($previousApproved[$boqItem->id] ?? 0), $currentQuantity, "items.$index.executed_quantity");

                if ($currentQuantity <= 0) {
                    $measurement->items()->where('boq_item_id', $boqItem->id)->whereNull('structure_id')->delete();
                    continue;
                }

                $measurement->items()->updateOrCreate(
                    ['boq_item_id' => $boqItem->id, 'structure_id' => null],
                    [
                        'business_id' => $this->businessId(),
                        'project_id' => $project->id,
                        'executed_quantity' => $currentQuantity,
                        'approved_quantity' => 0,
                        'notes' => $row['notes'] ?? null,
                    ]
                );
            }
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'msg' => __('construction::lang.measurement_quantities_saved'),
            ]);
        }

        return redirect()->route('construction.projects.measurements.show', [$project->id, $measurement->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.measurement_quantities_saved')]);
    }

    public function destroyItem(int $project, int $measurement, int $item)
    {
        $this->authorizePermission('construction.measurement.manage');
        $project = $this->findProject($project);
        $measurement = $this->findMeasurement($project, $measurement);
        $this->ensureEditable($measurement);
        $measurement->items()->where('business_id', $this->businessId())->findOrFail($item)->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'msg' => __('construction::lang.measurement_item_deleted')]);
        }

        return redirect()->route('construction.projects.measurements.show', [$project->id, $measurement->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.measurement_item_deleted')]);
    }

    public function approve(int $project, int $measurement)
    {
        $this->authorizePermission('construction.measurement.approve');
        $project = $this->findProject($project);
        $measurement = $this->findMeasurement($project, $measurement);
        $this->ensureEditable($measurement);
        $items = $measurement->items()->with('boqItem')->get();

        if ($items->isEmpty()) {
            return back()->with('status', ['success' => 0, 'msg' => __('construction::lang.cannot_approve_empty_measurement')]);
        }

        $errorMsg = null;
        DB::transaction(function () use ($measurement, $items, &$errorMsg) {
            foreach ($items as $item) {
                $previousApproved = (float) DB::table('construction_measurement_items as lines')
                    ->join('construction_measurements as headers', 'headers.id', '=', 'lines.measurement_id')
                    ->where('lines.boq_item_id', $item->boq_item_id)
                    ->where('headers.status', 'approved')
                    ->where('headers.id', '<>', $measurement->id)
                    ->sum('lines.approved_quantity');

                if ($previousApproved + (float) $item->executed_quantity > (float) $item->boqItem->contract_quantity + 0.0001) {
                    $errorMsg = __('construction::lang.measurement_exceeds_contract_quantity', ['code' => $item->boqItem->code]);
                    return;
                }
                $item->update(['approved_quantity' => $item->executed_quantity]);
            }

            if (! $errorMsg) {
                $measurement->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                AuditTrail::record('measurement_approved', $measurement, $measurement->project_id, ['status' => 'draft'], ['status' => 'approved']);
            }
        });

        if ($errorMsg) {
            return back()->with('status', ['success' => 0, 'msg' => $errorMsg]);
        }

        return redirect()->route('construction.projects.measurements.show', [$project->id, $measurement->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.measurement_approved')]);
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findMeasurement(ConstructionProject $project, int $id): ConstructionMeasurement
    {
        return $project->measurements()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function ensureEditable(ConstructionMeasurement $measurement): void
    {
        abort_unless($measurement->isEditable(), 422, __('construction::lang.approved_measurement_is_locked'));
    }

    private function previousApprovedQuantities(ConstructionProject $project, ConstructionMeasurement $measurement)
    {
        return DB::table('construction_measurement_items as lines')
            ->join('construction_measurements as headers', 'headers.id', '=', 'lines.measurement_id')
            ->where('lines.business_id', $this->businessId())
            ->where('lines.project_id', $project->id)
            ->where('headers.status', 'approved')
            ->where('headers.id', '<>', $measurement->id)
            ->selectRaw('lines.boq_item_id, SUM(lines.approved_quantity) as total')
            ->groupBy('lines.boq_item_id')
            ->pluck('total', 'lines.boq_item_id');
    }

    private function validateContractLimit(ConstructionBoqItem $boqItem, float $previousQuantity, float $currentQuantity, string $field): void
    {
        if ($previousQuantity + $currentQuantity > (float) $boqItem->contract_quantity + 0.0001) {
            throw ValidationException::withMessages([
                $field => __('construction::lang.measurement_exceeds_contract_quantity', ['code' => $boqItem->code]),
            ]);
        }
    }

    private function contractBoq(ConstructionProject $project, bool $required = true)
    {
        $boqVersionId = $project->contracts()
            ->where('status', 'active')
            ->whereNotNull('boq_version_id')
            ->value('boq_version_id');

        if (! $boqVersionId) {
            abort_if($required, 422, __('construction::lang.no_active_contract_for_measurement'));

            return null;
        }

        return $project->boqVersions()->where('business_id', $this->businessId())->findOrFail($boqVersionId);
    }
}
