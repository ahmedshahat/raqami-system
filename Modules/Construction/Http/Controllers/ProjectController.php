<?php

namespace Modules\Construction\Http\Controllers;

use App\Contact;
use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionAuditLog;
use Modules\Construction\Support\AuditTrail;
use Modules\Construction\Support\AuditDescription;

class ProjectController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizePermission('construction.project.view');

        $search = trim((string) $request->input('q'));
        $status = (string) $request->input('status');
        if (! in_array($status, ConstructionProject::STATUSES, true)) {
            $status = '';
        }

        $baseQuery = ConstructionProject::query()->where('business_id', $this->businessId());
        $summary = [
            'all' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        $projects = $baseQuery
            ->with(['customer:id,name,supplier_business_name', 'manager:id,surname,first_name,last_name', 'primaryContract'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->appends($request->only(['q', 'status']));

        return view('construction::projects.index', compact('projects', 'summary', 'search', 'status') + $this->formOptions() + [
            'suggestedCode' => $this->suggestedProjectCode(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('construction.project.create');

        return view('construction::projects.create', array_merge(
            $this->formOptions(),
            ['suggestedCode' => $this->suggestedProjectCode()]
        ));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.project.create');
        $this->normalizeConsultantInput($request);
        $validated = $this->validateProject($request);

        $project = DB::transaction(function () use ($validated) {
            $quote = null;
            if (! empty($validated['quote_id'])) {
                $quote = ConstructionQuote::where('business_id', $this->businessId())
                    ->where('status', 'accepted')->whereNull('project_id')
                    ->whereKey($validated['quote_id'])->lockForUpdate()->firstOrFail();
            }
            $projectData = $this->projectData($validated);
            if ($quote) {
                $projectData['customer_id'] = $quote->customer_id;
                $projectData['quote_id'] = $quote->id;
            }
            $projectData['status'] = 'draft';
            $project = ConstructionProject::create($projectData + [
                'business_id' => $this->businessId(),
                'created_by' => auth()->id(),
            ]);

            $boq = $project->boqVersions()->create([
                'business_id' => $this->businessId(),
                'version_number' => 1,
                'name' => __('construction::lang.boq_default_name'),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            if ($quote) {
                foreach ($quote->items()->orderBy('sort_order')->orderBy('id')->get() as $item) {
                    $boq->items()->create([
                        'business_id' => $this->businessId(), 'project_id' => $project->id,
                        'row_type' => 'item', 'code' => $item->code, 'description' => $item->description,
                        'unit_id' => $item->unit_id, 'unit' => $item->unit,
                        'contract_quantity' => $item->quantity, 'sales_unit_price' => $item->unit_price,
                        'sales_total' => $item->total, 'item_kind' => 'standard',
                        'sort_order' => $item->sort_order, 'notes' => $item->notes,
                    ]);
                }
                $boq->refreshTotals();
                $quote->update(['project_id' => $project->id]);
            }

            $this->syncMembers($project, $validated);
            AuditTrail::record('project_created', $project, $project->id, [], $project->only(['code', 'name', 'customer_id', 'quote_id', 'manager_id', 'location', 'start_date', 'end_date']));

            return $project;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $project->id,
                'url' => route('construction.projects.show', $project->id),
                'message' => __('construction::lang.project_created'),
            ], 201);
        }

        return redirect()->route('construction.projects.show', $project->id)->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.project_created'),
        ]);
    }

    public function show(int $project)
    {
        $this->authorizePermission('construction.project.view');

        $project = $this->findProject($project);
        $project->load([
            'quote:id,number,title,status,total,quote_date',
            'customer:id,name,supplier_business_name,mobile,email',
            'consultantContact:id,name,supplier_business_name,mobile,email',
            'manager:id,surname,first_name,last_name,username',
            'members:id,surname,first_name,last_name,username',
            'contracts' => fn ($query) => $query->with('boqVersion')->latest('id'),
            'boqVersions' => fn ($query) => $query->latest('version_number'),
            'measurements' => fn ($query) => $query->latest('measurement_date')->limit(5),
            'customerCertificates' => fn ($query) => $query->with('invoice')->latest('certificate_date')->limit(5),
            'contractAdjustments' => fn ($query) => $query->latest('id'),
        ]);
        $project->loadCount(['boqVersions', 'contracts', 'measurements', 'customerCertificates']);

        $workflow = [
            'project' => true,
            'boq' => $project->boqVersions()->whereHas('items', fn ($query) => $query->where('row_type', 'item'))->exists(),
            'contract' => $project->contracts()->where('status', 'active')->exists(),
            'execution' => $project->measurements()->where('status', 'approved')->exists(),
            'customer_certificates' => $project->customerCertificates()->whereIn('status', ['approved', 'partially_approved', 'posted', 'paid'])->exists(),
            'invoice' => $project->customerCertificates()->whereNotNull('invoice_transaction_id')->exists(),
        ];
        $auditLogs = ConstructionAuditLog::where('business_id', $this->businessId())->where('project_id', $project->id)
            ->with(['user:id,surname,first_name,last_name,username', 'auditable'])->latest('id')->limit(25)->get();
        $auditLogs->each(fn (ConstructionAuditLog $log) => $log->setAttribute('display_description', AuditDescription::for($log, $project)));

        $expenseQuery = Transaction::query()
            ->where('business_id', $this->businessId())
            ->where('construction_project_id', $project->id)
            ->whereIn('type', ['expense', 'expense_refund']);
        $expenseStats = (clone $expenseQuery)->selectRaw('COUNT(*) as expense_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense_refund' THEN -final_total ELSE final_total END), 0) as expense_total")
            ->first();
        $recentProjectExpenses = (clone $expenseQuery)
            ->with(['constructionProjectItem:id,code,description', 'location:id,name'])
            ->latest('transaction_date')->latest('id')->limit(5)->get();
        $materialTotal = (float) DB::table('construction_material_documents as docs')
            ->join('construction_material_document_lines as lines', 'lines.document_id', '=', 'docs.id')
            ->where('docs.business_id', $this->businessId())->where('docs.project_id', $project->id)->where('docs.status', 'approved')
            ->selectRaw("COALESCE(SUM(CASE WHEN docs.type = 'return' THEN -lines.total_cost ELSE lines.total_cost END), 0) as total")
            ->value('total');
        $laborTotal = (float) DB::table('construction_labor_sheets as sheets')
            ->join('construction_labor_sheet_lines as lines', 'lines.sheet_id', '=', 'sheets.id')
            ->where('sheets.business_id', $this->businessId())->where('sheets.project_id', $project->id)->where('sheets.status', 'approved')
            ->sum('lines.total_cost');
        $projectValue = (float) ($project->contracts->firstWhere('is_primary', true)?->original_value
            ?: $project->boqVersions->first()?->sales_total
            ?: $project->quote?->total
            ?: 0);
        $registeredCostTotal = (float) $expenseStats->expense_total + $materialTotal + $laborTotal;
        $projectCostSummary = [
            'project_value' => $projectValue,
            'expense_total' => (float) $expenseStats->expense_total,
            'material_total' => $materialTotal,
            'labor_total' => $laborTotal,
            'registered_total' => $registeredCostTotal,
            'expense_count' => (int) $expenseStats->expense_count,
            'expense_percent' => $projectValue > 0 ? max(0, round(($registeredCostTotal / $projectValue) * 100, 1)) : 0,
        ];

        return view('construction::projects.show', compact('project', 'workflow', 'auditLogs', 'projectCostSummary', 'recentProjectExpenses'));
    }

    public function edit(int $project)
    {
        $this->authorizePermission('construction.project.update');
        $project = $this->findProject($project);
        $project->load(['members:id']);

        return view('construction::projects.edit', array_merge(
            $this->formOptions(),
            compact('project')
        ));
    }

    public function update(Request $request, int $project)
    {
        $this->authorizePermission('construction.project.update');
        $project = $this->findProject($project);
        $this->normalizeConsultantInput($request);
        $validated = $this->validateProject($request, $project->id);

        if ($validated['status'] === 'active') {
            abort_unless($project->contracts()->where('status', 'active')->whereNotNull('boq_version_id')->exists(), 422, __('construction::lang.project_requires_active_contract'));
        }

        DB::transaction(function () use ($project, $validated) {
            $project->update($this->projectData($validated));
            $this->syncMembers($project, $validated);
        });

        return redirect()->route('construction.projects.show', $project->id)->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.project_updated'),
        ]);
    }

    public function destroy(int $project)
    {
        $this->authorizePermission('construction.project.delete');
        $project = $this->findProject($project);

        abort_unless($project->status === 'draft', 422, __('construction::lang.only_draft_project_can_be_deleted'));
        abort_if($project->quote_id, 422, __('construction::lang.quote_project_cannot_delete'));
        $project->delete();

        return redirect()->route('construction.projects.index')->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.project_deleted'),
        ]);
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    private function validateProject(Request $request, ?int $projectId = null): array
    {
        $businessId = $this->businessId();
        $this->normalizeBusinessDates($request, ['start_date', 'end_date']);

        return $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('construction_projects', 'code')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($projectId),
            ],
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => [
                'required', 'integer',
                Rule::exists('contacts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->whereIn('type', ['customer', 'both'])
                    ->whereNull('deleted_at')),
            ],
            'consultant_contact_id' => [
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')),
            ],
            'consultant_selection' => ['required', 'string'],
            'consultant_name' => ['nullable', 'required_if:consultant_selection,manual', 'string', 'max:190'],
            'manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(ConstructionProject::STATUSES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [
                'integer', 'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')),
            ],
            'quote_id' => [
                'nullable', 'integer',
                Rule::exists('construction_quotes', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)->where('status', 'accepted')->whereNull('project_id')),
            ],
        ]);
    }

    private function projectData(array $validated): array
    {
        return collect($validated)->only([
            'code', 'name', 'customer_id', 'consultant_contact_id', 'consultant_name',
            'manager_id', 'location', 'start_date', 'end_date', 'status', 'description',
        ])->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
    }

    private function syncMembers(ConstructionProject $project, array $validated): void
    {
        $memberIds = collect($validated['member_ids'] ?? [])
            ->push($validated['manager_id'] ?? null)
            ->filter()
            ->unique()
            ->values();
        $now = now();
        $sync = [];

        foreach ($memberIds as $userId) {
            $sync[(int) $userId] = [
                'business_id' => $this->businessId(),
                'access_level' => (int) $userId === (int) ($validated['manager_id'] ?? 0) ? 'manage' : 'view',
                'assigned_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $project->members()->sync($sync);
    }

    private function formOptions(): array
    {
        $businessId = $this->businessId();
        $contacts = Contact::query()
            ->where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'supplier_business_name']);
        $consultants = Contact::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'supplier_business_name']);
        $users = User::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'surname', 'first_name', 'last_name', 'username']);

        $acceptedQuotes = ConstructionQuote::query()
            ->where('business_id', $businessId)
            ->where('status', 'accepted')
            ->whereNull('project_id')
            ->with('customer:id,name,supplier_business_name')
            ->orderByDesc('quote_date')
            ->get(['id', 'number', 'title', 'customer_id', 'total', 'quote_date']);

        return compact('contacts', 'consultants', 'users', 'acceptedQuotes');
    }

    private function suggestedProjectCode(): string
    {
        $highest = ConstructionProject::withTrashed()
            ->where('business_id', $this->businessId())
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^PRJ-(\d+)$/i', trim($code), $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'PRJ-'.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }

    private function normalizeConsultantInput(Request $request): void
    {
        $selection = (string) $request->input('consultant_selection', 'none');

        if ($selection === 'manual') {
            $request->merge(['consultant_contact_id' => null]);

            return;
        }

        if (ctype_digit($selection) && (int) $selection > 0) {
            $request->merge([
                'consultant_contact_id' => (int) $selection,
                'consultant_name' => null,
            ]);

            return;
        }

        $request->merge([
            'consultant_selection' => 'none',
            'consultant_contact_id' => null,
            'consultant_name' => null,
        ]);
    }
}
