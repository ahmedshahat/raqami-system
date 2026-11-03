<?php

namespace Modules\Construction\Http\Controllers;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Construction\Entities\ConstructionContract;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Support\CostAnalytics;

class DashboardController extends BaseController
{
    public function index()
    {
        return redirect()->route('construction.overview.index');
    }

    public function overview(bool $isLegacyRoot = false)
    {
        $this->authorizePermission('construction.access');

        $businessId = $this->businessId();
        $projects = ConstructionProject::query()->where('business_id', $businessId);
        $summary = [
            'all' => (clone $projects)->count(),
            'active' => (clone $projects)->where('status', 'active')->count(),
            'draft' => (clone $projects)->where('status', 'draft')->count(),
            'on_hold' => (clone $projects)->where('status', 'on_hold')->count(),
            'completed' => (clone $projects)->where('status', 'completed')->count(),
            'cancelled' => (clone $projects)->where('status', 'cancelled')->count(),
        ];

        $contractSummary = ConstructionContract::query()
            ->where('business_id', $businessId)
            ->selectRaw('COUNT(*) as contracts_count')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contracts")
            ->selectRaw('COALESCE(SUM(original_value), 0) as total_value')
            ->selectRaw('COALESCE(SUM(advance_payment_value), 0) as advance_value')
            ->selectRaw('COALESCE(SUM(performance_bond_value), 0) as bond_value')
            ->selectRaw('COALESCE(SUM(original_value * retention_percent / 100), 0) as retention_value')
            ->first();

        $recentProjects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->with([
                'customer:id,name,supplier_business_name',
                'manager:id,surname,first_name,last_name,username',
                'primaryContract',
            ])
            ->latest('id')
            ->limit(4)
            ->get();

        $acceptedQuoteCount = ConstructionQuote::query()->where('business_id', $businessId)
            ->where('status', 'accepted')->whereNull('project_id')->count();

        $completionRate = $summary['all'] > 0
            ? round(($summary['completed'] / $summary['all']) * 100)
            : 0;

        $statusBars = collect([
            'active' => $summary['active'],
            'draft' => $summary['draft'],
            'on_hold' => $summary['on_hold'],
            'completed' => $summary['completed'],
            'cancelled' => $summary['cancelled'],
        ])->map(fn ($count) => [
            'count' => $count,
            'percent' => $summary['all'] > 0 ? round(($count / $summary['all']) * 100) : 0,
        ]);

        $viewName = $isLegacyRoot ? 'construction::dashboard.index' : 'construction::dashboard.overview';

        return view($viewName, compact(
            'summary',
            'contractSummary',
            'recentProjects',
            'acceptedQuoteCount',
            'completionRate',
            'statusBars'
        ));
    }

    public function subcontractors()
    {
        $this->authorizePermission('construction.access');

        return view('construction::dashboard.subcontractors');
    }

    public function costs(Request $request)
    {
        $this->authorizePermission('construction.access');

        $businessId = $this->businessId();
        $projects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $selectedProjectId = $request->integer('project_id') ?: null;
        if ($selectedProjectId && ! $projects->contains('id', $selectedProjectId)) {
            abort(404);
        }

        // الفترة: من/إلى بتنسيق النشاط أو Y-m-d؛ لو التاريخين معكوسين نبدّلهم
        $this->normalizeBusinessDates($request, ['from', 'to']);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $fromDate = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $toDate = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;
        if ($fromDate && $toDate && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $expenseQuery = Transaction::query()
            ->where('business_id', $businessId)
            ->whereNotNull('construction_project_id')
            ->whereIn('type', ['expense', 'expense_refund'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('construction_expense_cancellations as expense_cancellations')
                ->whereColumn('expense_cancellations.expense_transaction_id', 'transactions.id'))
            ->when($selectedProjectId, fn ($query) => $query->where('construction_project_id', $selectedProjectId))
            ->when($fromDate, fn ($query) => $query->whereDate('transaction_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('transaction_date', '<=', $toDate));

        $expenseStats = (clone $expenseQuery)
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('COUNT(DISTINCT construction_project_id) as projects_count')
            ->first();

        $recentExpenses = (clone $expenseQuery)
            ->with([
                'constructionProject:id,code,name',
                'constructionProjectItem:id,code,description',
                'location:id,name',
            ])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(10)
            ->get();

        $materialCounts = ConstructionMaterialDocument::query()
            ->join('construction_material_document_lines as material_lines', 'material_lines.document_id', '=', 'construction_material_documents.id')
            ->where('construction_material_documents.business_id', $businessId)
            ->where('construction_material_documents.status', 'approved')
            ->when($selectedProjectId, fn ($query) => $query->where('construction_material_documents.project_id', $selectedProjectId))
            ->when($fromDate, fn ($query) => $query->whereDate('construction_material_documents.document_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('construction_material_documents.document_date', '<=', $toDate))
            ->selectRaw("SUM(CASE WHEN construction_material_documents.type = 'issue' THEN 1 ELSE 0 END) as issue_lines")
            ->selectRaw("SUM(CASE WHEN construction_material_documents.type = 'return' THEN 1 ELSE 0 END) as return_lines")
            ->first();

        $laborLines = ConstructionLaborSheet::query()
            ->join('construction_labor_sheet_lines as labor_lines', 'labor_lines.sheet_id', '=', 'construction_labor_sheets.id')
            ->where('construction_labor_sheets.business_id', $businessId)
            ->where('construction_labor_sheets.status', 'approved')
            ->when($selectedProjectId, fn ($query) => $query->where('construction_labor_sheets.project_id', $selectedProjectId))
            ->when($fromDate, fn ($query) => $query->whereDate('construction_labor_sheets.period_end', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('construction_labor_sheets.period_end', '<=', $toDate))
            ->count('labor_lines.id');

        $subcontractCertificates = ConstructionSubcontractCertificate::query()
            ->where('business_id', $businessId)
            ->where('status', 'approved')
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
            ->when($fromDate, fn ($query) => $query->whereDate('certificate_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('certificate_date', '<=', $toDate))
            ->count();

        $analytics = (new CostAnalytics())->build($businessId, $selectedProjectId, $fromDate, $toDate);

        $summary = [
            'total' => $analytics['total'],
            'expenses' => $analytics['sources']['expenses'],
            'materials' => $analytics['sources']['materials'],
            'labor' => $analytics['sources']['labor'],
            'labor_lines' => (int) $laborLines,
            'subcontracts' => $analytics['sources']['subcontracts'],
            'subcontract_certificates' => (int) $subcontractCertificates,
            'records_count' => (int) $expenseStats->records_count,
            'projects_count' => (int) $expenseStats->projects_count,
            'material_issue_lines' => (int) $materialCounts->issue_lines,
            'material_return_lines' => (int) $materialCounts->return_lines,
        ];

        return view('construction::dashboard.costs', compact(
            'projects',
            'selectedProjectId',
            'fromDate',
            'toDate',
            'summary',
            'analytics',
            'recentExpenses'
        ));
    }

    public function reports()
    {
        $this->authorizePermission('construction.access');

        return view('construction::dashboard.reports');
    }
}
