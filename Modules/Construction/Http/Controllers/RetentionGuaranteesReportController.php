<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\RetentionGuaranteesReport;

class RetentionGuaranteesReportController extends BaseController
{
    public function index(Request $request, RetentionGuaranteesReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        return view('construction::dashboard.retention-guarantees', $this->reportData($request, $reportBuilder));
    }

    public function print(Request $request, RetentionGuaranteesReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder);
        $data['business'] = Business::findOrFail($this->businessId());
        $data['businessLocation'] = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();
        return view('construction::dashboard.retention-guarantees-print', $data);
    }

    private function reportData(Request $request, RetentionGuaranteesReport $reportBuilder): array
    {
        $this->normalizeBusinessDates($request, ['to_date']);
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'], 'to_date' => ['nullable', 'date'],
            'party_type' => ['nullable', Rule::in(['customer', 'subcontractor'])],
            'due_status' => ['nullable', Rule::in(['not_due', 'upcoming', 'due', 'no_date', 'closed'])],
        ]);
        $projects = ConstructionProject::query()->where('business_id', $this->businessId())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'code', 'name']);
        $projectId = ! empty($validated['project_id']) ? (int) $validated['project_id'] : null;
        if ($projectId && ! $projects->contains('id', $projectId)) abort(404);
        $toDate = $validated['to_date'] ?? now()->toDateString();
        $partyType = $validated['party_type'] ?? null; $dueStatus = $validated['due_status'] ?? null;
        $report = $reportBuilder->build($this->businessId(), $projectId, $toDate, $partyType, $dueStatus);
        $selectedProject = $projectId ? $projects->firstWhere('id', $projectId) : null;
        return compact('projects', 'selectedProject', 'projectId', 'toDate', 'partyType', 'dueStatus', 'report');
    }
}
