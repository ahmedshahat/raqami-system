<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionBoqVersion;
use Modules\Construction\Entities\ConstructionContract;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\PrintPdfFactory;
use Modules\Construction\Support\AuditTrail;

class ContractController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizePermission('construction.contract.view');

        $businessId = $this->businessId();
        $contractSummary = ConstructionContract::query()
            ->where('business_id', $businessId)
            ->selectRaw('COUNT(*) as contracts_count')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contracts")
            ->selectRaw('COALESCE(SUM(original_value), 0) as total_value')
            ->selectRaw('COALESCE(SUM(advance_payment_value), 0) as advance_value')
            ->selectRaw('COALESCE(SUM(performance_bond_value), 0) as bond_value')
            ->selectRaw('COALESCE(SUM(original_value * retention_percent / 100), 0) as retention_value')
            ->first();

        $contracts = ConstructionContract::query()
            ->where('business_id', $businessId)
            ->with('project:id,business_id,code,name,status')
            ->latest('id')
            ->paginate(20);

        return view('construction::contracts.index', compact('contractSummary', 'contracts'));
    }

    public function create(int $project)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);

        if ($primary = $project->primaryContract) {
            return redirect()->route('construction.projects.contracts.show', [$project->id, $primary->id])
                ->with('status', ['success' => 0, 'msg' => __('construction::lang.primary_contract_already_exists')]);
        }

        return view('construction::contracts.create', [
            'project' => $project,
            'approvedBoqs' => $this->approvedBoqs($project),
            'suggestedContractNumber' => $this->suggestedContractNumber($project),
        ]);
    }

    public function store(Request $request, int $project)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);

        if ($primary = $project->primaryContract) {
            return redirect()->route('construction.projects.contracts.show', [$project->id, $primary->id])
                ->with('status', ['success' => 0, 'msg' => __('construction::lang.primary_contract_already_exists')]);
        }

        $validated = $this->validateContract($request, $project);

        $contract = DB::transaction(function () use ($project, $validated) {
            $lockedProject = ConstructionProject::query()
                ->where('business_id', $this->businessId())
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();
            $sequence = max(
                1,
                (int) $lockedProject->next_contract_sequence,
                (int) $lockedProject->contracts()->max('sequence_number') + 1
            );
            $contractNumber = $this->formatContractNumber($lockedProject->code, $sequence);
            $boq = $this->selectedBoq($lockedProject, $validated['boq_version_id'] ?? null);

            $contract = $lockedProject->contracts()->create($this->contractData($validated, $boq) + [
                'business_id' => $this->businessId(),
                'sequence_number' => $sequence,
                'contract_number' => $contractNumber,
                'is_primary' => true,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $lockedProject->update(['next_contract_sequence' => $sequence + 1]);

            return $contract;
        });

        return redirect()->route('construction.projects.contracts.show', [$project->id, $contract->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.contract_draft_created')]);
    }

    public function show(int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.view');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        $contract->load(['boqVersion', 'activatedBy:id,surname,first_name,last_name,username'])->loadCount(['adjustments', 'adjustments as approved_adjustments_count' => fn ($query) => $query->where('status', 'approved')]);

        return view('construction::contracts.show', compact('project', 'contract'));
    }

    public function edit(int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);

        if (! $contract->isEditable()) {
            return redirect()->route('construction.projects.contracts.show', [$project->id, $contract->id])
                ->with('status', ['success' => 0, 'msg' => __('construction::lang.active_contract_is_locked')]);
        }

        return view('construction::contracts.edit', [
            'project' => $project,
            'contract' => $contract,
            'approvedBoqs' => $this->approvedBoqs($project, $contract->boq_version_id),
        ]);
    }

    public function update(Request $request, int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.manage');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);

        if (! $contract->isEditable()) {
            return redirect()->route('construction.projects.contracts.show', [$project->id, $contract->id])
                ->with('status', ['success' => 0, 'msg' => __('construction::lang.active_contract_is_locked')]);
        }

        $validated = $this->validateContract($request, $project, $contract->id);
        $boq = $this->selectedBoq($project, $validated['boq_version_id'] ?? null);
        $contract->update($this->contractData($validated, $boq));

        return redirect()->route('construction.projects.contracts.show', [$project->id, $contract->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.contract_draft_updated')]);
    }

    public function activate(int $project, int $contract)
    {
        $this->authorizePermission('construction.contract.approve');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);

        if (! $contract->isEditable()) {
            return back()->with('status', ['success' => 0, 'msg' => __('construction::lang.active_contract_is_locked')]);
        }

        $contract->load('boqVersion');

        if (! ($contract->boqVersion && in_array($contract->boqVersion->status, ['draft', 'approved'], true))) {
            return back()->with('status', ['success' => 0, 'msg' => __('construction::lang.contract_requires_approved_boq')]);
        }

        if (empty($contract->contract_number)) {
            return back()->with('status', ['success' => 0, 'msg' => __('construction::lang.contract_requires_number')]);
        }

        $signedAt = $contract->signed_at ?: now()->toDateString();

        DB::transaction(function () use ($project, $contract, $signedAt) {
            $contract->boqVersion->refreshTotals();
            if ($contract->boqVersion->status === 'draft') {
                $contract->boqVersion->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }
            $project->contracts()->where('id', '<>', $contract->id)->update(['is_primary' => false]);
            $contract->update([
                'signed_at' => $signedAt,
                'original_value' => $contract->boqVersion->sales_total,
                'is_primary' => true,
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => auth()->id(),
            ]);

            if ($project->status !== 'active') {
                $project->update(['status' => 'active']);
            }
            AuditTrail::record('contract_approved', $contract, $project->id, ['status' => 'draft'], ['status' => 'active', 'original_value' => $contract->boqVersion->sales_total]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.contract_activated')]);
    }

    public function print(int $project, int $contract)
    {
        $data = $this->printData($project, $contract);
        $data['autoPrint'] = true;
        $data['pdfMode'] = false;

        return view('construction::contracts.print', $data);
    }

    public function preview(int $project, int $contract)
    {
        $data = $this->printData($project, $contract);
        $data['autoPrint'] = false;
        $data['pdfMode'] = false;

        return view('construction::contracts.print', $data);
    }

    public function pdf(int $project, int $contract)
    {
        $data = $this->printData($project, $contract);
        $data['autoPrint'] = false;
        $data['pdfMode'] = true;
        $safeNumber = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $data['contract']->contract_number), '-');
        $fileName = 'contract-'.($safeNumber ?: $data['contract']->id).'.pdf';

        $mpdf = PrintPdfFactory::make('P');
        if ($data['contract']->status === 'draft') {
            $mpdf->SetWatermarkText(__('construction::lang.draft_not_for_signature'), 0.08);
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = PrintPdfFactory::FONT;
        }
        $mpdf->WriteHTML(view('construction::contracts.print_pdf', $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function printData(int $project, int $contract): array
    {
        $this->authorizePermission('construction.contract.view');
        $project = $this->findProject($project);
        $contract = $this->findContract($project, $contract);
        $contract->load(['boqVersion.items' => fn ($query) => $query->where('item_kind', '!=', 'variation')->orderBy('sort_order')->orderBy('id')]);
        $project->load(['customer', 'consultantContact']);
        $business = Business::with(['locations' => fn ($query) => $query
            ->active()
            ->orderBy('id')])
            ->find($this->businessId());
        $businessLocation = $business?->locations->first();

        return compact('project', 'contract', 'business', 'businessLocation');
    }

    private function validateContract(Request $request, ConstructionProject $project, ?int $contractId = null): array
    {
        $this->normalizeBusinessDates($request, ['signed_at']);
        $this->normalizeLocalizedNumbers($request, [
            'original_value', 'advance_payment_value', 'retention_percent', 'performance_bond_value',
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'contract_type' => ['required', Rule::in(ConstructionContract::TYPES)],
            'boq_version_id' => [
                'required', 'integer',
                Rule::exists('construction_boq_versions', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())
                    ->where('project_id', $project->id)
                    ->whereIn('status', ['draft', 'approved'])),
            ],
            'signed_at' => ['nullable', 'date'],
            'original_value' => ['nullable', 'numeric', 'min:0'],
            'advance_payment_value' => ['nullable', 'numeric', 'min:0'],
            'retention_percent' => ['nullable', 'numeric', 'between:0,100'],
            'performance_bond_value' => ['nullable', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function contractData(array $validated, ConstructionBoqVersion $boq): array
    {
        return [
            'boq_version_id' => $boq?->id,
            'title' => trim($validated['title']),
            'contract_type' => $validated['contract_type'],
            'signed_at' => !empty($validated['signed_at']) ? $validated['signed_at'] : now()->toDateString(),
            'original_value' => $boq->sales_total,
            'advance_payment_value' => $validated['advance_payment_value'] ?? 0,
            'retention_percent' => $validated['retention_percent'] ?? 0,
            'performance_bond_value' => $validated['performance_bond_value'] ?? 0,
            'payment_terms_days' => $validated['payment_terms_days'] ?? null,
            'warranty_months' => $validated['warranty_months'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function approvedBoqs(ConstructionProject $project, ?int $includeId = null)
    {
        return $project->boqVersions()
            ->where(function ($query) use ($includeId) {
                $query->whereIn('status', ['draft', 'approved']);
                if ($includeId) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->latest('version_number')
            ->get();
    }

    private function selectedBoq(ConstructionProject $project, $boqId): ConstructionBoqVersion
    {
        return $project->boqVersions()->where('business_id', $this->businessId())->whereIn('status', ['draft', 'approved'])->findOrFail($boqId);
    }

    private function suggestedContractNumber(ConstructionProject $project): string
    {
        $sequence = max(
            1,
            (int) $project->next_contract_sequence,
            (int) $project->contracts()->max('sequence_number') + 1
        );

        return $this->formatContractNumber($project->code, $sequence);
    }

    private function formatContractNumber(string $projectCode, int $sequence): string
    {
        return trim($projectCode).'-C'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findContract(ConstructionProject $project, int $id): ConstructionContract
    {
        return $project->contracts()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function ensureEditable(ConstructionContract $contract): void
    {
        abort_unless($contract->isEditable(), 422, __('construction::lang.active_contract_is_locked'));
    }
}
