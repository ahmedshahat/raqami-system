<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Support\SubcontractorStatementReport;
use Modules\Construction\Support\ReportDateRange;

class SubcontractorStatementController extends BaseController
{
    public function index(Request $request, SubcontractorStatementReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        return view('construction::dashboard.subcontractor-statement', $this->reportData($request, $reportBuilder));
    }

    public function print(Request $request, SubcontractorStatementReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder, true);
        $data['business'] = Business::findOrFail($this->businessId());
        $data['businessLocation'] = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();
        return view('construction::dashboard.subcontractor-statement-print', $data);
    }

    private function reportData(Request $request, SubcontractorStatementReport $reportBuilder, bool $requireSubcontractor = false): array
    {
        $this->normalizeBusinessDates($request, ['from_date', 'to_date']);
        $validated = $request->validate([
            'subcontractor_id' => [$requireSubcontractor ? 'required' : 'nullable', 'integer'], 'project_id' => ['nullable', 'integer'],
            'date_range' => ['nullable', Rule::in(ReportDateRange::PRESETS)],
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $subcontractorIds = ConstructionSubcontract::query()->where('business_id', $this->businessId())->distinct()->pluck('subcontractor_id');
        $subcontractors = Contact::query()->where('business_id', $this->businessId())->whereIn('id', $subcontractorIds)
            ->whereIn('type', ['supplier', 'both'])->orderBy('name')->get(['id', 'name', 'supplier_business_name']);
        $projects = ConstructionProject::query()->where('business_id', $this->businessId())->orderBy('name')->get(['id', 'code', 'name']);
        $selectedSubcontractor = null; $selectedProject = null; $statement = null;
        ['dateRange' => $dateRange, 'fromDate' => $fromDate, 'toDate' => $toDate] = ReportDateRange::resolve(
            $validated['date_range'] ?? null,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );
        $projectId = ! empty($validated['project_id']) ? (int) $validated['project_id'] : null;
        if ($projectId) { $selectedProject = $projects->firstWhere('id', $projectId); abort_unless($selectedProject, 404); }
        if (! empty($validated['subcontractor_id'])) {
            $selectedSubcontractor = Contact::query()->where('business_id', $this->businessId())->whereIn('id', $subcontractorIds)
                ->whereIn('type', ['supplier', 'both'])->findOrFail($validated['subcontractor_id']);
            $statement = $reportBuilder->build($selectedSubcontractor, $projectId, $fromDate, $toDate);
        }
        return compact('subcontractors', 'projects', 'selectedSubcontractor', 'selectedProject', 'projectId', 'dateRange', 'fromDate', 'toDate', 'statement');
    }
}
