<?php

namespace Modules\Construction\Support;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;

class ProjectFinancialPositionReport
{
    private const APPROVED_CUSTOMER_CERTIFICATE_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];

    public function build(ConstructionProject $project, ?string $fromDate, string $toDate): array
    {
        $project->loadMissing([
            'customer:id,name,supplier_business_name',
            'manager:id,surname,first_name,last_name,username',
            'primaryContract.boqVersion',
        ]);

        $contract = $project->primaryContract;
        $approvedAdjustments = $contract
            ? (float) $contract->adjustments()->where('status', 'approved')->whereDate('adjustment_date', '<=', $toDate)->sum('total')
            : 0.0;
        $contractValue = (float) ($contract?->original_value ?? 0) + $approvedAdjustments;
        $estimatedCost = (float) ($contract?->boqVersion?->estimated_cost_total ?? $project->approvedBoq?->estimated_cost_total ?? 0);

        $customerCertificatesQuery = ConstructionCustomerCertificate::query()
            ->where('business_id', $project->business_id)
            ->where('project_id', $project->id)
            ->whereIn('status', self::APPROVED_CUSTOMER_CERTIFICATE_STATUSES)
            ->whereDate('certificate_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('certificate_date', '>=', $fromDate));

        $customerCertificates = (clone $customerCertificatesQuery)
            ->with('invoice:id,invoice_no,final_total,payment_status,status')
            ->latest('certificate_date')
            ->latest('id')
            ->get();
        $approvedWork = (float) $customerCertificates->sum('current_approved_gross');
        $customerRetention = (float) $customerCertificates->sum('retention_value');
        $customerNetDue = (float) $customerCertificates->sum('net_due');
        $invoiceIds = ConstructionCustomerCertificate::query()
            ->where('business_id', $project->business_id)
            ->where('project_id', $project->id)
            ->whereNotNull('invoice_transaction_id')
            ->whereDate('certificate_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('certificate_date', '>=', $fromDate))
            ->pluck('invoice_transaction_id');
        $invoiced = (float) Transaction::query()
            ->where('business_id', $project->business_id)
            ->whereIn('id', $invoiceIds)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->sum('final_total');
        $collected = $invoiceIds->isEmpty() ? 0.0 : (float) DB::table('transaction_payments')
            ->whereIn('transaction_id', $invoiceIds)
            ->whereDate('paid_on', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('paid_on', '>=', $fromDate))
            ->selectRaw('COALESCE(SUM(CASE WHEN is_return = 1 THEN -amount ELSE amount END), 0) as total')
            ->value('total');

        $expenseCost = (float) Transaction::query()
            ->where('business_id', $project->business_id)
            ->where('construction_project_id', $project->id)
            ->whereIn('type', ['expense', 'expense_refund'])
            ->whereDate('transaction_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('transaction_date', '>=', $fromDate))
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense_refund' THEN -final_total ELSE final_total END), 0) as total")
            ->value('total');

        $materialCost = (float) ConstructionMaterialDocument::query()
            ->where('construction_material_documents.business_id', $project->business_id)
            ->where('construction_material_documents.project_id', $project->id)
            ->where('construction_material_documents.status', 'approved')
            ->whereDate('construction_material_documents.document_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('construction_material_documents.document_date', '>=', $fromDate))
            ->join('construction_material_document_lines as report_material_lines', 'report_material_lines.document_id', '=', 'construction_material_documents.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN construction_material_documents.type = 'return' THEN -report_material_lines.total_cost ELSE report_material_lines.total_cost END), 0) as total")
            ->value('total');

        $laborCost = (float) ConstructionLaborSheet::query()
            ->where('construction_labor_sheets.business_id', $project->business_id)
            ->where('construction_labor_sheets.project_id', $project->id)
            ->where('construction_labor_sheets.status', 'approved')
            ->whereDate('construction_labor_sheets.period_end', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('construction_labor_sheets.period_end', '>=', $fromDate))
            ->join('construction_labor_sheet_lines as report_labor_lines', 'report_labor_lines.sheet_id', '=', 'construction_labor_sheets.id')
            ->sum('report_labor_lines.total_cost');

        $subcontractCertificatesQuery = ConstructionSubcontractCertificate::query()
            ->where('business_id', $project->business_id)
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereDate('certificate_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('certificate_date', '>=', $fromDate));
        $subcontractCertificates = (clone $subcontractCertificatesQuery)
            ->with(['subcontract.subcontractor:id,name,supplier_business_name'])
            ->latest('certificate_date')
            ->get();
        $subcontractCertificateIds = $subcontractCertificates->pluck('id');
        $subcontractCost = (float) $subcontractCertificates->sum('gross_value');
        $subcontractNet = (float) $subcontractCertificates->sum('net_value');
        $subcontractRetention = (float) $subcontractCertificates->sum('retention_value');
        $releasedRetention = $subcontractCertificateIds->isEmpty() ? 0.0 : (float) ConstructionSubcontractRetentionRelease::query()
            ->where('business_id', $project->business_id)
            ->whereIn('certificate_id', $subcontractCertificateIds)
            ->where('status', 'recorded')
            ->whereDate('release_date', '<=', $toDate)
            ->sum('amount');
        $subcontractPaid = $subcontractCertificateIds->isEmpty() ? 0.0 : (float) ConstructionSubcontractPayment::query()
            ->where('business_id', $project->business_id)
            ->whereIn('certificate_id', $subcontractCertificateIds)
            ->where('status', 'recorded')
            ->whereDate('payment_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('payment_date', '>=', $fromDate))
            ->sum('amount');

        $costs = [
            'materials' => round($materialCost, 4),
            'labor' => round($laborCost, 4),
            'subcontracts' => round($subcontractCost, 4),
            'expenses' => round($expenseCost, 4),
        ];
        $actualCost = round(array_sum($costs), 4);
        $currentResult = round($approvedWork - $actualCost, 4);

        $subcontractors = $subcontractCertificates
            ->groupBy('subcontract_id')
            ->map(function ($certificates) use ($project, $toDate, $fromDate) {
                $first = $certificates->first();
                $ids = $certificates->pluck('id');
                $released = (float) ConstructionSubcontractRetentionRelease::query()
                    ->where('business_id', $project->business_id)->whereIn('certificate_id', $ids)
                    ->where('status', 'recorded')->whereDate('release_date', '<=', $toDate)->sum('amount');
                $paid = (float) ConstructionSubcontractPayment::query()
                    ->where('business_id', $project->business_id)->whereIn('certificate_id', $ids)
                    ->where('status', 'recorded')->whereDate('payment_date', '<=', $toDate)
                    ->when($fromDate, fn ($query) => $query->whereDate('payment_date', '>=', $fromDate))->sum('amount');
                $net = (float) $certificates->sum('net_value');
                $retention = (float) $certificates->sum('retention_value');
                $payable = $net + $released;

                return [
                    'name' => $first->subcontract?->subcontractor?->supplier_business_name ?: $first->subcontract?->subcontractor?->name ?: '—',
                    'agreement' => $first->subcontract?->number ?: '—',
                    'certificates' => $certificates->count(),
                    'gross' => round((float) $certificates->sum('gross_value'), 4),
                    'retention' => round($retention - $released, 4),
                    'payable' => round($payable, 4),
                    'paid' => round($paid, 4),
                    'balance' => round(max(0, $payable - $paid), 4),
                ];
            })->values();

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'contract' => [
                'original' => round((float) ($contract?->original_value ?? 0), 4),
                'adjustments' => round($approvedAdjustments, 4),
                'value' => round($contractValue, 4),
                'estimated_cost' => round($estimatedCost, 4),
                'budget_margin' => round($contractValue - $estimatedCost, 4),
                'budget_margin_percent' => $contractValue > 0 ? round((($contractValue - $estimatedCost) / $contractValue) * 100, 2) : 0,
            ],
            'customer' => [
                'approved_work' => round($approvedWork, 4),
                'retention' => round($customerRetention, 4),
                'net_due' => round($customerNetDue, 4),
                'invoiced' => round($invoiced, 4),
                'collected' => round($collected, 4),
                'receivable' => round(max(0, $invoiced - $collected), 4),
                'certificates_count' => $customerCertificates->count(),
            ],
            'costs' => $costs + [
                'actual' => $actualCost,
                'variance_to_budget' => round($estimatedCost - $actualCost, 4),
                'consumption_percent' => $estimatedCost > 0 ? round(($actualCost / $estimatedCost) * 100, 2) : 0,
            ],
            'result' => [
                'current' => $currentResult,
                'margin_percent' => $approvedWork > 0 ? round(($currentResult / $approvedWork) * 100, 2) : 0,
            ],
            'subcontracts' => [
                'gross' => round($subcontractCost, 4),
                'net' => round($subcontractNet, 4),
                'retention' => round(max(0, $subcontractRetention - $releasedRetention), 4),
                'released_retention' => round($releasedRetention, 4),
                'payable' => round($subcontractNet + $releasedRetention, 4),
                'paid' => round($subcontractPaid, 4),
                'balance' => round(max(0, $subcontractNet + $releasedRetention - $subcontractPaid), 4),
                'certificates_count' => $subcontractCertificates->count(),
            ],
            'cash' => [
                'customer_collections' => round($collected, 4),
                'subcontractor_payments' => round($subcontractPaid, 4),
                'partial_net' => round($collected - $subcontractPaid, 4),
            ],
            'customer_certificates' => $customerCertificates,
            'subcontractors' => $subcontractors,
            'warnings' => collect([
                ! $contract ? __('construction::lang.financial_report_warning_no_contract') : null,
                $estimatedCost <= 0 ? __('construction::lang.financial_report_warning_no_budget') : null,
                $estimatedCost > 0 && $actualCost > $estimatedCost ? __('construction::lang.financial_report_warning_over_budget') : null,
                $invoiced > $collected + 0.0001 ? __('construction::lang.financial_report_warning_receivables') : null,
            ])->filter()->values(),
        ];
    }
}
