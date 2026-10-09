<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\CustomerCertificatesCollectionReport;
use Modules\Construction\Support\ReportDateRange;

class CustomerCertificatesCollectionController extends BaseController
{
    public function index(Request $request, CustomerCertificatesCollectionReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        return view('construction::dashboard.customer-certificates-collection', $this->reportData($request, $reportBuilder));
    }

    public function print(Request $request, CustomerCertificatesCollectionReport $reportBuilder)
    {
        $this->authorizePermission('construction.access');
        $data = $this->reportData($request, $reportBuilder);
        $data['business'] = Business::findOrFail($this->businessId());
        $data['businessLocation'] = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();
        return view('construction::dashboard.customer-certificates-collection-print', $data);
    }

    private function reportData(Request $request, CustomerCertificatesCollectionReport $reportBuilder): array
    {
        $this->normalizeBusinessDates($request, ['from_date', 'to_date']);
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'],
            'date_range' => ['nullable', Rule::in(ReportDateRange::PRESETS)],
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'collection_status' => ['nullable', Rule::in(['unbilled', 'unpaid', 'partial', 'paid'])],
        ]);
        $projects = ConstructionProject::query()->where('business_id', $this->businessId())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'code', 'name', 'customer_id']);
        $customers = Contact::query()->where('business_id', $this->businessId())->whereIn('id', $projects->pluck('customer_id')->filter()->unique())
            ->orderBy('name')->get(['id', 'name', 'supplier_business_name']);
        $projectId = ! empty($validated['project_id']) ? (int) $validated['project_id'] : null;
        $customerId = ! empty($validated['customer_id']) ? (int) $validated['customer_id'] : null;
        if ($projectId && ! $projects->contains('id', $projectId)) abort(404);
        if ($customerId && ! $customers->contains('id', $customerId)) abort(404);
        ['dateRange' => $dateRange, 'fromDate' => $fromDate, 'toDate' => $toDate] = ReportDateRange::resolve(
            $validated['date_range'] ?? null,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );
        $collectionStatus = $validated['collection_status'] ?? null;
        $report = $reportBuilder->build($this->businessId(), $projectId, $customerId, $fromDate, $toDate, $collectionStatus);
        $selectedProject = $projectId ? $projects->firstWhere('id', $projectId) : null;
        $selectedCustomer = $customerId ? $customers->firstWhere('id', $customerId) : null;
        return compact('projects', 'customers', 'selectedProject', 'selectedCustomer', 'projectId', 'customerId', 'dateRange', 'fromDate', 'toDate', 'collectionStatus', 'report');
    }
}
