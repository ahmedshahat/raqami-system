<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use Illuminate\Http\Request;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\BoqSubcontractComparisonReport;

class BoqSubcontractComparisonController extends BaseController
{
    public function index(Request $request, BoqSubcontractComparisonReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        return view('construction::dashboard.boq-subcontract-comparison', $this->reportData($request, $reportBuilder));
    }

    public function print(Request $request, BoqSubcontractComparisonReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder, true);
        $data['business'] = Business::findOrFail($this->businessId());
        $data['businessLocation'] = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();
        return view('construction::dashboard.boq-subcontract-comparison-print', $data);
    }

    private function reportData(Request $request, BoqSubcontractComparisonReport $reportBuilder, bool $requireProject = false): array
    {
        $this->normalizeBusinessDates($request, ['to_date']);
        $validated = $request->validate(['project_id' => [$requireProject ? 'required' : 'nullable', 'integer'], 'to_date' => ['nullable', 'date']]);
        $projects = ConstructionProject::query()->where('business_id', $this->businessId())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'code', 'name', 'status']);
        $selectedProject = null; $report = null; $toDate = $validated['to_date'] ?? now()->toDateString();
        if (! empty($validated['project_id'])) {
            $selectedProject = ConstructionProject::query()->where('business_id', $this->businessId())->findOrFail($validated['project_id']);
            $report = $reportBuilder->build($selectedProject, $toDate);
        }
        return compact('projects', 'selectedProject', 'toDate', 'report');
    }
}
