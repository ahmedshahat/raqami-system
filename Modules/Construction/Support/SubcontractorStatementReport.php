<?php

namespace Modules\Construction\Support;

use App\Contact;
use Illuminate\Support\Collection;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;

class SubcontractorStatementReport
{
    public function build(Contact $subcontractor, ?int $projectId, ?string $fromDate, string $toDate): array
    {
        $contractIds = ConstructionSubcontract::query()
            ->where('business_id', $subcontractor->business_id)
            ->where('subcontractor_id', $subcontractor->id)
            ->where('status', 'approved')
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->pluck('id');
        $contractsValue = (float) ConstructionSubcontract::query()->whereIn('id', $contractIds)->sum('total_value');
        $allCertificates = ConstructionSubcontractCertificate::query()
            ->where('business_id', $subcontractor->business_id)->whereIn('subcontract_id', $contractIds)
            ->where('status', 'approved')->whereDate('certificate_date', '<=', $toDate)
            ->with(['project:id,code,name', 'subcontract:id,number,title'])->get();
        $certificateIds = $allCertificates->pluck('id');
        $allReleases = $certificateIds->isEmpty() ? collect() : ConstructionSubcontractRetentionRelease::query()
            ->where('business_id', $subcontractor->business_id)->whereIn('certificate_id', $certificateIds)
            ->where('status', 'recorded')->whereDate('release_date', '<=', $toDate)
            ->with(['certificate.project:id,code,name', 'subcontract:id,number,title'])->get();
        $allPayments = $certificateIds->isEmpty() ? collect() : ConstructionSubcontractPayment::query()
            ->where('business_id', $subcontractor->business_id)->whereIn('certificate_id', $certificateIds)
            ->where('status', 'recorded')->whereDate('payment_date', '<=', $toDate)
            ->with(['certificate.project:id,code,name', 'subcontract:id,number,title', 'account:id,name'])->get();

        $openingBalance = 0.0;
        if ($fromDate) {
            $openingBalance = (float) $allCertificates->where('certificate_date', '<', $fromDate)->sum('net_value')
                + (float) $allReleases->where('release_date', '<', $fromDate)->sum('amount')
                - (float) $allPayments->where('payment_date', '<', $fromDate)->sum('amount');
        }

        $certificateMovements = $this->inPeriod($allCertificates, 'certificate_date', $fromDate, $toDate)->map(fn ($certificate) => [
            'date' => $certificate->certificate_date->toDateString(), 'sort' => 10, 'type' => 'certificate', 'number' => $certificate->number,
            'project' => trim(($certificate->project?->code ?: '').' — '.($certificate->project?->name ?: ''), ' —'), 'agreement' => $certificate->subcontract?->number,
            'description' => __('construction::lang.statement_certificate_description', ['gross' => CurrencyFormatter::parts($certificate->gross_value)['amount'], 'retention' => CurrencyFormatter::parts($certificate->retention_value)['amount']]),
            'debit' => 0.0, 'credit' => (float) $certificate->net_value,
        ]);
        $releaseMovements = $this->inPeriod($allReleases, 'release_date', $fromDate, $toDate)->map(fn ($release) => [
            'date' => $release->release_date->toDateString(), 'sort' => 20, 'type' => 'retention_release', 'number' => $release->number,
            'project' => trim(($release->certificate?->project?->code ?: '').' — '.($release->certificate?->project?->name ?: ''), ' —'), 'agreement' => $release->subcontract?->number,
            'description' => $release->notes ?: __('construction::lang.statement_retention_release_description'), 'debit' => 0.0, 'credit' => (float) $release->amount,
        ]);
        $paymentMovements = $this->inPeriod($allPayments, 'payment_date', $fromDate, $toDate)->map(fn ($payment) => [
            'date' => $payment->payment_date->toDateString(), 'sort' => 30, 'type' => 'payment', 'number' => $payment->number,
            'project' => trim(($payment->certificate?->project?->code ?: '').' — '.($payment->certificate?->project?->name ?: ''), ' —'), 'agreement' => $payment->subcontract?->number,
            'description' => collect([$payment->reference_no, $payment->account?->name, $payment->notes])->filter()->implode(' — ') ?: __('construction::lang.statement_payment_description'),
            'debit' => (float) $payment->amount, 'credit' => 0.0,
        ]);

        $runningBalance = round($openingBalance, 4);
        $movements = $certificateMovements->concat($releaseMovements)->concat($paymentMovements)
            ->sortBy(fn ($movement) => $movement['date'].'-'.str_pad((string) $movement['sort'], 2, '0', STR_PAD_LEFT).'-'.$movement['number'])->values()
            ->map(function ($movement) use (&$runningBalance) {
                $runningBalance = round($runningBalance + $movement['credit'] - $movement['debit'], 4);
                $movement['balance'] = $runningBalance;
                return $movement;
            });

        $gross = (float) $allCertificates->sum('gross_value');
        $net = (float) $allCertificates->sum('net_value');
        $retention = (float) $allCertificates->sum('retention_value');
        $released = (float) $allReleases->sum('amount');
        $paid = (float) $allPayments->sum('amount');

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'summary' => [
                'contracts_value' => round($contractsValue, 4), 'gross' => round($gross, 4),
                'deductions' => round(max(0, $gross - $net - $retention), 4),
                'retention_original' => round($retention, 4), 'retention_released' => round($released, 4),
                'retention_remaining' => round(max(0, $retention - $released), 4), 'payable' => round($net + $released, 4),
                'paid' => round($paid, 4), 'balance' => round($net + $released - $paid, 4),
                'opening_balance' => round($openingBalance, 4), 'period_debit' => round((float) $movements->sum('debit'), 4),
                'period_credit' => round((float) $movements->sum('credit'), 4), 'closing_balance' => round($runningBalance, 4),
                'certificates_count' => $allCertificates->count(),
            ],
            'movements' => $movements,
            'agreements_count' => $contractIds->count(),
        ];
    }

    private function inPeriod(Collection $records, string $dateField, ?string $fromDate, string $toDate): Collection
    {
        return $records->filter(function ($record) use ($dateField, $fromDate, $toDate) {
            $date = $record->{$dateField}->toDateString();
            return $date <= $toDate && (! $fromDate || $date >= $fromDate);
        })->values();
    }
}
