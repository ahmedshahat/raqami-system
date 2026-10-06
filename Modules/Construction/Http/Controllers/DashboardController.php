<?php

namespace Modules\Construction\Http\Controllers;

use App\Transaction;
use Illuminate\Http\Request;
use Modules\Construction\Entities\ConstructionContract;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionLaborSheet;

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

        $expenseQuery = Transaction::query()
            ->where('business_id', $businessId)
            ->whereNotNull('construction_project_id')
            ->whereIn('type', ['expense', 'expense_refund']);

        if ($selectedProjectId) {
            $expenseQuery->where('construction_project_id', $selectedProjectId);
        }

        $expenseStats = (clone $expenseQuery)
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('COUNT(DISTINCT construction_project_id) as projects_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense_refund' THEN -final_total ELSE final_total END), 0) as net_total")
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

        $materialQuery = ConstructionMaterialDocument::query()
            ->where('construction_material_documents.business_id', $businessId)
            ->where('construction_material_documents.status', 'approved')
            ->when($selectedProjectId, fn ($query) => $query->where('construction_material_documents.project_id', $selectedProjectId));
        $materialStats = (clone $materialQuery)
            ->join('construction_material_document_lines as material_lines', 'material_lines.document_id', '=', 'construction_material_documents.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN construction_material_documents.type = 'return' THEN -material_lines.total_cost ELSE material_lines.total_cost END), 0) as net_total")
            ->selectRaw("SUM(CASE WHEN construction_material_documents.type = 'issue' THEN 1 ELSE 0 END) as issue_lines")
            ->selectRaw("SUM(CASE WHEN construction_material_documents.type = 'return' THEN 1 ELSE 0 END) as return_lines")
            ->first();

        $laborStats = ConstructionLaborSheet::query()
            ->where('construction_labor_sheets.business_id', $businessId)
            ->where('construction_labor_sheets.status', 'approved')
            ->when($selectedProjectId, fn ($query) => $query->where('construction_labor_sheets.project_id', $selectedProjectId))
            ->join('construction_labor_sheet_lines as labor_lines', 'labor_lines.sheet_id', '=', 'construction_labor_sheets.id')
            ->selectRaw('COALESCE(SUM(labor_lines.total_cost), 0) as total')
            ->selectRaw('COUNT(labor_lines.id) as lines_count')
            ->first();

        $summary = [
            'total' => (float) $expenseStats->net_total + (float) $materialStats->net_total + (float) $laborStats->total,
            'expenses' => (float) $expenseStats->net_total,
            'materials' => (float) $materialStats->net_total,
            'labor' => (float) $laborStats->total,
            'labor_lines' => (int) $laborStats->lines_count,
            'records_count' => (int) $expenseStats->records_count,
            'projects_count' => (int) $expenseStats->projects_count,
            'material_issue_lines' => (int) $materialStats->issue_lines,
            'material_return_lines' => (int) $materialStats->return_lines,
        ];

        return view('construction::dashboard.costs', compact(
            'projects',
            'selectedProjectId',
            'summary',
            'recentExpenses'
        ));
    }

    public function reports()
    {
        $this->authorizePermission('construction.access');

        return view('construction::dashboard.reports');
    }
}
