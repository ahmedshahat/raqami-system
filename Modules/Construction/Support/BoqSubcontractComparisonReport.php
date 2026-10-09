<?php

namespace Modules\Construction\Support;

use Modules\Construction\Entities\ConstructionCustomerCertificateItem;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontractCertificateItem;
use Modules\Construction\Entities\ConstructionSubcontractItem;

class BoqSubcontractComparisonReport
{
    private const CUSTOMER_APPROVED_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];
    private const AGREEMENT_ACTIVE_STATUSES = ['approved', 'completed'];

    public function build(ConstructionProject $project, string $toDate): array
    {
        $project->loadMissing(['customer:id,name,supplier_business_name', 'primaryContract.boqVersion', 'approvedBoq']);
        $boq = $project->primaryContract?->boqVersion ?: $project->approvedBoq;

        if (! $boq) {
            return $this->emptyReport($toDate);
        }

        $boqItems = $boq->items()->where('row_type', 'item')->orderBy('sort_order')->orderBy('id')->get();
        $boqItemIds = $boqItems->pluck('id');

        $agreementItems = ConstructionSubcontractItem::query()
            ->where('business_id', $project->business_id)->where('project_id', $project->id)
            ->whereHas('subcontract', fn ($query) => $query->whereIn('status', self::AGREEMENT_ACTIVE_STATUSES))
            ->with(['subcontract.subcontractor:id,name,supplier_business_name'])->get();
        $linkedAgreementItems = $agreementItems->whereIn('boq_item_id', $boqItemIds)->groupBy('boq_item_id');
        $unlinkedAgreementValue = (float) $agreementItems->whereNull('boq_item_id')->sum('total_value')
            + (float) $agreementItems->whereNotNull('boq_item_id')->whereNotIn('boq_item_id', $boqItemIds)->sum('total_value');

        $customerActual = ConstructionCustomerCertificateItem::query()
            ->where('business_id', $project->business_id)->where('project_id', $project->id)
            ->whereIn('boq_item_id', $boqItemIds)
            ->whereHas('certificate', fn ($query) => $query->whereIn('status', self::CUSTOMER_APPROVED_STATUSES)->whereDate('certificate_date', '<=', $toDate))
            ->get()->groupBy('boq_item_id');

        $subcontractActualItems = ConstructionSubcontractCertificateItem::query()
            ->where('business_id', $project->business_id)
            ->whereHas('certificate', fn ($query) => $query->where('project_id', $project->id)->where('status', 'approved')->whereDate('certificate_date', '<=', $toDate))
            ->whereHas('subcontractItem.subcontract', fn ($query) => $query->whereIn('status', self::AGREEMENT_ACTIVE_STATUSES))
            ->with(['subcontractItem.subcontract.subcontractor:id,name,supplier_business_name'])->get();
        $linkedActualItems = $subcontractActualItems->filter(fn ($item) => $boqItemIds->contains($item->subcontractItem?->boq_item_id))
            ->groupBy(fn ($item) => $item->subcontractItem->boq_item_id);
        $unlinkedActualValue = (float) $subcontractActualItems
            ->reject(fn ($item) => $boqItemIds->contains($item->subcontractItem?->boq_item_id))->sum('current_value');

        $rows = $boqItems->map(function ($item) use ($linkedAgreementItems, $customerActual, $linkedActualItems) {
            $agreements = $linkedAgreementItems->get($item->id, collect());
            $actualSubcontracts = $linkedActualItems->get($item->id, collect());
            $contractSales = (float) $item->sales_total;
            $contractAssignment = (float) $agreements->sum('total_value');
            $actualSales = (float) $customerActual->get($item->id, collect())->sum('approved_amount');
            $actualAssignment = (float) $actualSubcontracts->sum('current_value');
            $expectedMargin = $contractSales - $contractAssignment;
            $actualMargin = $actualSales - $actualAssignment;
            $names = $agreements->map(fn ($agreement) => $this->subcontractorName($agreement->subcontract?->subcontractor))
                ->concat($actualSubcontracts->map(fn ($actual) => $this->subcontractorName($actual->subcontractItem?->subcontract?->subcontractor)))
                ->filter()->unique()->values()->implode('، ');

            return [
                'id' => $item->id, 'code' => $item->code, 'description' => $item->description, 'unit' => $item->unit,
                'contract_quantity' => (float) $item->contract_quantity, 'sales_unit_price' => (float) $item->sales_unit_price,
                'subcontractors' => $names ?: '—',
                'contract' => $this->comparison($contractSales, $contractAssignment, $expectedMargin),
                'actual' => $this->comparison($actualSales, $actualAssignment, $actualMargin),
            ];
        })->values();

        $contractSales = (float) $rows->sum('contract.sales');
        $contractAssignment = (float) $rows->sum('contract.assignment') + $unlinkedAgreementValue;
        $actualSales = (float) $rows->sum('actual.sales');
        $actualAssignment = (float) $rows->sum('actual.assignment') + $unlinkedActualValue;

        return [
            'period' => ['to' => $toDate], 'boq' => $boq,
            'rows' => $rows,
            'contract' => $this->summary($contractSales, $contractAssignment, $rows->where('contract.status', 'loss')->count()),
            'actual' => $this->summary($actualSales, $actualAssignment, $rows->where('actual.status', 'loss')->count()),
            'unlinked' => ['contract' => round($unlinkedAgreementValue, 4), 'actual' => round($unlinkedActualValue, 4)],
            'warnings' => collect([
                $unlinkedAgreementValue > 0 ? __('construction::lang.comparison_warning_unlinked_contract', ['amount' => CurrencyFormatter::format($unlinkedAgreementValue)]) : null,
                $unlinkedActualValue > 0 ? __('construction::lang.comparison_warning_unlinked_actual', ['amount' => CurrencyFormatter::format($unlinkedActualValue)]) : null,
            ])->filter()->values(),
        ];
    }

    private function comparison(float $sales, float $assignment, float $margin): array
    {
        $percent = $sales > 0 ? ($margin / $sales) * 100 : ($assignment > 0 ? -100 : 0);
        $status = $margin < -0.0001 ? 'loss' : ($assignment <= 0.0001 ? 'not_assigned' : ($percent < 10 ? 'low' : 'healthy'));

        return ['sales' => round($sales, 4), 'assignment' => round($assignment, 4), 'margin' => round($margin, 4), 'margin_percent' => round($percent, 2), 'status' => $status];
    }

    private function summary(float $sales, float $assignment, int $lossItems): array
    {
        $margin = $sales - $assignment;
        return ['sales' => round($sales, 4), 'assignment' => round($assignment, 4), 'margin' => round($margin, 4),
            'margin_percent' => $sales > 0 ? round(($margin / $sales) * 100, 2) : 0, 'loss_items' => $lossItems];
    }

    private function subcontractorName($contact): ?string
    {
        return $contact?->supplier_business_name ?: $contact?->name;
    }

    private function emptyReport(string $toDate): array
    {
        $zero = ['sales' => 0.0, 'assignment' => 0.0, 'margin' => 0.0, 'margin_percent' => 0.0, 'loss_items' => 0];
        return ['period' => ['to' => $toDate], 'boq' => null, 'rows' => collect(), 'contract' => $zero, 'actual' => $zero,
            'unlinked' => ['contract' => 0.0, 'actual' => 0.0], 'warnings' => collect([__('construction::lang.comparison_warning_no_boq')])];
    }
}
