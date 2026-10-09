<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\ProjectFinancialPositionReport;
use Modules\Construction\Support\ReportDateRange;

class FinancialReportController extends BaseController
{
    public function index(Request $request, ProjectFinancialPositionReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder);

        return view('construction::dashboard.reports', $data);
    }

    public function print(Request $request, ProjectFinancialPositionReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder, true);
        abort_unless($data['selectedProject'], 422, __('construction::lang.financial_report_select_project'));
        $data['business'] = Business::findOrFail($this->businessId());
        $data['businessLocation'] = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();

        return view('construction::dashboard.financial-report-print', $data);
    }

    private function reportData(Request $request, ProjectFinancialPositionReport $reportBuilder, bool $requireProject = false): array
    {
        $this->normalizeBusinessDates($request, ['from_date', 'to_date']);
        $validated = $request->validate([
            'project_id' => [$requireProject ? 'required' : 'nullable', 'integer'],
            'date_range' => ['nullable', Rule::in(ReportDateRange::PRESETS)],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $projects = ConstructionProject::query()
            ->where('business_id', $this->businessId())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status', 'start_date']);
        $selectedProject = null;
        $report = null;
        ['dateRange' => $dateRange, 'fromDate' => $fromDate, 'toDate' => $toDate] = ReportDateRange::resolve(
            $validated['date_range'] ?? null,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );

        if (! empty($validated['project_id'])) {
            $selectedProject = ConstructionProject::query()
                ->where('business_id', $this->businessId())
                ->findOrFail($validated['project_id']);
            $report = $reportBuilder->build($selectedProject, $fromDate, $toDate);
        }

        return compact('projects', 'selectedProject', 'dateRange', 'fromDate', 'toDate', 'report');
    }
}
