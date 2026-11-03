<?php

namespace Modules\Construction\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionCustomerCertificate;

class CustomerCertificatesCollectionReport
{
    private const APPROVED_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];
    private const COLLECTION_STATUSES = ['unbilled', 'unpaid', 'partial', 'paid'];

    public function build(int $businessId, ?int $projectId, ?int $customerId, ?string $fromDate, string $toDate, ?string $collectionStatus): array
    {
        $certificates = ConstructionCustomerCertificate::query()
            ->where('business_id', $businessId)->whereIn('status', self::APPROVED_STATUSES)
            ->whereDate('certificate_date', '<=', $toDate)
            ->when($fromDate, fn ($query) => $query->whereDate('certificate_date', '>=', $fromDate))
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($customerId, fn ($query) => $query->whereHas('project', fn ($project) => $project->where('customer_id', $customerId)))
            ->with(['project:id,code,name,customer_id', 'project.customer:id,name,supplier_business_name',
                'invoice:id,invoice_no,transaction_date,final_total,payment_status,pay_term_number,pay_term_type,status'])
            ->orderBy('certificate_date')->orderBy('id')->get();

        $invoiceIds = $certificates->pluck('invoice_transaction_id')->filter()->unique()->values();
        $payments = $invoiceIds->isEmpty() ? collect() : DB::table('transaction_payments')
            ->whereIn('transaction_id', $invoiceIds)->whereDate('paid_on', '<=', $toDate)
            ->selectRaw('transaction_id, COALESCE(SUM(CASE WHEN is_return = 1 THEN -amount ELSE amount END), 0) as total')
            ->groupBy('transaction_id')->pluck('total', 'transaction_id');
        $reportDate = Carbon::parse($toDate)->endOfDay();

        $rows = $certificates->map(function ($certificate) use ($payments, $reportDate) {
            $invoice = $certificate->invoice;
            $invoiceAmount = (float) ($invoice?->final_total ?? 0);
            $collected = $invoice ? (float) ($payments[$invoice->id] ?? 0) : 0.0;
            $outstanding = max(0, $invoiceAmount - $collected);
            $status = ! $invoice ? 'unbilled' : ($outstanding <= 0.0001 ? 'paid' : ($collected > 0.0001 ? 'partial' : 'unpaid'));
            $dueDate = $invoice?->due_date;
            $ageDays = $invoice && $outstanding > 0 ? max(0, $dueDate->diffInDays($reportDate, false)) : 0;
            $deductions = (float) $certificate->advance_recovery_value + (float) $certificate->other_deductions_value;

            return [
                'certificate' => $certificate, 'project' => $certificate->project,
                'customer' => $certificate->project?->customer, 'invoice' => $invoice,
                'approved_work' => round((float) $certificate->current_approved_gross, 4),
                'retention' => round((float) $certificate->retention_value, 4), 'deductions' => round($deductions, 4),
                'tax' => round((float) $certificate->tax_value, 4), 'net_due' => round((float) $certificate->net_due, 4),
                'invoice_amount' => round($invoiceAmount, 4), 'collected' => round($collected, 4),
                'outstanding' => round($outstanding, 4), 'status' => $status, 'due_date' => $dueDate,
                'age_days' => $ageDays, 'invoice_difference' => round($invoiceAmount - (float) $certificate->net_due, 4),
            ];
        })->when($collectionStatus && in_array($collectionStatus, self::COLLECTION_STATUSES, true),
            fn ($rows) => $rows->where('status', $collectionStatus))->values();

        $aging = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'over_90' => 0.0];
        foreach ($rows->whereIn('status', ['unpaid', 'partial']) as $row) {
            $bucket = $row['age_days'] <= 0 ? 'current' : ($row['age_days'] <= 30 ? 'days_1_30' : ($row['age_days'] <= 60 ? 'days_31_60' : ($row['age_days'] <= 90 ? 'days_61_90' : 'over_90')));
            $aging[$bucket] += $row['outstanding'];
        }

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate], 'rows' => $rows,
            'summary' => [
                'approved_work' => round((float) $rows->sum('approved_work'), 4),
                'retention' => round((float) $rows->sum('retention'), 4), 'deductions' => round((float) $rows->sum('deductions'), 4),
                'tax' => round((float) $rows->sum('tax'), 4), 'net_due' => round((float) $rows->sum('net_due'), 4),
                'invoiced' => round((float) $rows->sum('invoice_amount'), 4), 'collected' => round((float) $rows->sum('collected'), 4),
                'outstanding' => round((float) $rows->sum('outstanding'), 4),
                'unbilled' => round((float) $rows->where('status', 'unbilled')->sum('net_due'), 4),
                'unbilled_count' => $rows->where('status', 'unbilled')->count(),
                'partial_count' => $rows->where('status', 'partial')->count(), 'unpaid_count' => $rows->where('status', 'unpaid')->count(),
                'paid_count' => $rows->where('status', 'paid')->count(), 'certificates_count' => $rows->count(),
                'invoice_differences_count' => $rows->filter(fn ($row) => $row['invoice'] && abs($row['invoice_difference']) > 0.01)->count(),
            ],
            'aging' => collect($aging)->map(fn ($value) => round($value, 4))->all(),
            'filters' => ['project_id' => $projectId, 'customer_id' => $customerId, 'collection_status' => $collectionStatus],
        ];
    }
}
