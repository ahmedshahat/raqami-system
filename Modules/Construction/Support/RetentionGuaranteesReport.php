<?php

namespace Modules\Construction\Support;

use Carbon\Carbon;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;

class RetentionGuaranteesReport
{
    private const CUSTOMER_APPROVED_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];
    private const DUE_STATUSES = ['not_due', 'upcoming', 'due', 'no_date', 'closed'];

    public function build(int $businessId, ?int $projectId, string $toDate, ?string $partyType, ?string $dueStatus): array
    {
        $reportDate = Carbon::parse($toDate)->startOfDay();
        $customerRows = ConstructionCustomerCertificate::query()
            ->where('business_id', $businessId)->whereIn('status', self::CUSTOMER_APPROVED_STATUSES)
            ->where('retention_value', '>', 0)->whereDate('certificate_date', '<=', $toDate)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->with(['project:id,code,name,customer_id', 'project.customer:id,name,supplier_business_name'])
            ->orderBy('retention_due_date')->orderBy('certificate_date')->get()
            ->map(function ($certificate) use ($reportDate) {
                $amount = (float) $certificate->retention_value;
                $schedule = $this->schedule($certificate->retention_due_date, $reportDate, $amount);
                return ['party_type' => 'customer', 'project' => $certificate->project,
                    'party_name' => $certificate->project?->customer?->supplier_business_name ?: $certificate->project?->customer?->name ?: '—',
                    'certificate' => $certificate, 'original' => round($amount, 4), 'released' => 0.0,
                    'remaining' => round($amount, 4), 'release_status' => 'held'] + $schedule;
            });

        $subcontractCertificates = ConstructionSubcontractCertificate::query()
            ->where('business_id', $businessId)->where('status', 'approved')->where('retention_value', '>', 0)
            ->whereDate('certificate_date', '<=', $toDate)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->with(['project:id,code,name', 'subcontract:id,number,subcontractor_id', 'subcontract.subcontractor:id,name,supplier_business_name'])
            ->orderBy('retention_due_date')->orderBy('certificate_date')->get();
        $certificateIds = $subcontractCertificates->pluck('id');
        $releases = $certificateIds->isEmpty() ? collect() : ConstructionSubcontractRetentionRelease::query()
            ->where('business_id', $businessId)->whereIn('certificate_id', $certificateIds)->where('status', 'recorded')
            ->whereDate('release_date', '<=', $toDate)->selectRaw('certificate_id, SUM(amount) as total')->groupBy('certificate_id')->pluck('total', 'certificate_id');
        $subcontractorRows = $subcontractCertificates->map(function ($certificate) use ($releases, $reportDate) {
            $original = (float) $certificate->retention_value;
            $released = min($original, (float) ($releases[$certificate->id] ?? 0));
            $remaining = max(0, $original - $released);
            $schedule = $this->schedule($certificate->retention_due_date, $reportDate, $remaining);
            return ['party_type' => 'subcontractor', 'project' => $certificate->project,
                'party_name' => $certificate->subcontract?->subcontractor?->supplier_business_name ?: $certificate->subcontract?->subcontractor?->name ?: '—',
                'certificate' => $certificate, 'agreement_number' => $certificate->subcontract?->number,
                'original' => round($original, 4), 'released' => round($released, 4), 'remaining' => round($remaining, 4),
                'release_status' => $remaining <= 0.0001 ? 'released' : ($released > 0.0001 ? 'partial' : 'held')] + $schedule;
        });

        $allRows = $customerRows->concat($subcontractorRows);
        $filteredRows = $allRows
            ->when($partyType === 'customer', fn ($rows) => $rows->where('party_type', 'customer'))
            ->when($partyType === 'subcontractor', fn ($rows) => $rows->where('party_type', 'subcontractor'))
            ->when($dueStatus && in_array($dueStatus, self::DUE_STATUSES, true), fn ($rows) => $rows->where('due_status', $dueStatus))
            ->sortBy(fn ($row) => ($row['retention_due_date']?->format('Y-m-d') ?: '9999-12-31').'-'.$row['certificate']->id)->values();

        $visibleCustomers = $filteredRows->where('party_type', 'customer');
        $visibleSubcontractors = $filteredRows->where('party_type', 'subcontractor');
        $customerTotal = (float) $visibleCustomers->sum('remaining');
        $subcontractOriginal = (float) $visibleSubcontractors->sum('original');
        $subcontractReleased = (float) $visibleSubcontractors->sum('released');
        $subcontractRemaining = (float) $visibleSubcontractors->sum('remaining');

        return [
            'rows' => $filteredRows, 'customer_rows' => $visibleCustomers->values(), 'subcontractor_rows' => $visibleSubcontractors->values(),
            'summary' => ['customer_retention' => round($customerTotal, 4), 'subcontract_original' => round($subcontractOriginal, 4),
                'subcontract_released' => round($subcontractReleased, 4), 'subcontract_remaining' => round($subcontractRemaining, 4),
                'net_position' => round($customerTotal - $subcontractRemaining, 4),
                'due_customer' => round((float) $visibleCustomers->where('due_status', 'due')->sum('remaining'), 4),
                'due_subcontractor' => round((float) $visibleSubcontractors->where('due_status', 'due')->sum('remaining'), 4),
                'upcoming_customer' => round((float) $visibleCustomers->where('due_status', 'upcoming')->sum('remaining'), 4),
                'upcoming_subcontractor' => round((float) $visibleSubcontractors->where('due_status', 'upcoming')->sum('remaining'), 4),
                'no_date_count' => $filteredRows->where('due_status', 'no_date')->count()],
            'filters' => ['project_id' => $projectId, 'to_date' => $toDate, 'party_type' => $partyType, 'due_status' => $dueStatus],
        ];
    }

    private function schedule($dueDate, Carbon $reportDate, float $remaining): array
    {
        if ($remaining <= 0.0001) return ['retention_due_date' => $dueDate, 'due_status' => 'closed', 'days_until_due' => null];
        if (! $dueDate) return ['retention_due_date' => null, 'due_status' => 'no_date', 'days_until_due' => null];
        $days = $reportDate->diffInDays($dueDate->copy()->startOfDay(), false);
        $status = $days <= 0 ? 'due' : ($days <= 30 ? 'upcoming' : 'not_due');
        return ['retention_due_date' => $dueDate, 'due_status' => $status, 'days_until_due' => $days];
    }
}
