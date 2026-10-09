<?php

namespace Modules\Construction\Support;

use App\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionBoqCostAllocation;
use Modules\Construction\Entities\ConstructionProject;

class BoqCostProfitabilityReport
{
    public function build(ConstructionProject $project, string $toDate): array
    {
        $project->loadMissing(['customer:id,name,supplier_business_name', 'primaryContract.boqVersion', 'approvedBoq']);
        $boq = $project->primaryContract?->boqVersion ?: $project->approvedBoq;
        if (! $boq) {
            return $this->emptyReport($toDate);
        }

        $items = $boq->items()->where('row_type', 'item')->orderBy('sort_order')->orderBy('id')->get();
        $itemIds = $items->pluck('id');
        $estimated = ConstructionBoqCostAllocation::query()->where('boq_version_id', $boq->id)
            ->with('costCode:id,category')->get()->groupBy('boq_item_id');

        $materials = DB::table('construction_material_document_lines as lines')
            ->join('construction_material_documents as documents', 'documents.id', '=', 'lines.document_id')
            ->where('documents.business_id', $project->business_id)->where('documents.project_id', $project->id)
            ->where('documents.status', 'approved')->whereDate('documents.document_date', '<=', $toDate)
            ->get(['lines.boq_item_id', 'lines.total_cost', 'documents.type'])
            ->map(fn ($line) => (object) ['boq_item_id' => $line->boq_item_id, 'amount' => ($line->type === 'return' ? -1 : 1) * (float) $line->total_cost]);
        $labor = DB::table('construction_labor_sheet_lines as lines')
            ->join('construction_labor_sheets as sheets', 'sheets.id', '=', 'lines.sheet_id')
            ->where('sheets.business_id', $project->business_id)->where('sheets.project_id', $project->id)
            ->where('sheets.status', 'approved')->whereDate('sheets.period_end', '<=', $toDate)
            ->get(['lines.boq_item_id', 'lines.total_cost'])->map(fn ($line) => (object) ['boq_item_id' => $line->boq_item_id, 'amount' => (float) $line->total_cost]);
        $subcontracts = DB::table('construction_subcontract_certificate_items as lines')
            ->join('construction_subcontract_certificates as certificates', 'certificates.id', '=', 'lines.certificate_id')
            ->join('construction_subcontract_items as agreement_items', 'agreement_items.id', '=', 'lines.subcontract_item_id')
            ->where('certificates.business_id', $project->business_id)->where('certificates.project_id', $project->id)
            ->where('certificates.status', 'approved')->whereDate('certificates.certificate_date', '<=', $toDate)
            ->get(['agreement_items.boq_item_id', 'lines.current_value'])->map(fn ($line) => (object) ['boq_item_id' => $line->boq_item_id, 'amount' => (float) $line->current_value]);
        $expenses = Transaction::query()->where('business_id', $project->business_id)->where('construction_project_id', $project->id)
            ->whereIn('type', ['expense', 'expense_refund'])->whereDate('transaction_date', '<=', $toDate)
            ->get(['construction_boq_item_id as boq_item_id', 'type', 'final_total'])
            ->map(fn ($expense) => (object) ['boq_item_id' => $expense->boq_item_id, 'amount' => ($expense->type === 'expense_refund' ? -1 : 1) * (float) $expense->final_total]);

        $materialByItem = $this->groupCosts($materials);
        $laborByItem = $this->groupCosts($labor);
        $subcontractByItem = $this->groupCosts($subcontracts);
        $expenseByItem = $this->groupCosts($expenses);

        $rows = $items->map(function ($item) use ($estimated, $materialByItem, $laborByItem, $subcontractByItem, $expenseByItem) {
            $allocations = $estimated->get($item->id, collect());
            $estimatedByCategory = $allocations->groupBy(fn ($allocation) => $allocation->costCode?->category ?: 'overhead')
                ->map(fn ($group) => (float) $group->sum('estimated_cost'));
            $estimatedTotal = (float) $allocations->sum('estimated_cost');
            $actual = [
                'materials' => (float) ($materialByItem[$item->id] ?? 0),
                'labor' => (float) ($laborByItem[$item->id] ?? 0),
                'subcontracts' => (float) ($subcontractByItem[$item->id] ?? 0),
                'expenses' => (float) ($expenseByItem[$item->id] ?? 0),
            ];
            $actualTotal = array_sum($actual);
            $variance = $estimatedTotal - $actualTotal;
            $usage = $estimatedTotal > 0 ? ($actualTotal / $estimatedTotal) * 100 : ($actualTotal > 0 ? 100 : 0);
            $status = $actualTotal > $estimatedTotal + 0.0001 ? 'over' : ($estimatedTotal > 0 && $usage >= 90 ? 'near' : ($actualTotal > 0 ? 'healthy' : 'no_cost'));

            return [
                'id' => $item->id, 'code' => $item->code, 'description' => $item->description, 'unit' => $item->unit,
                'sales' => round((float) $item->sales_total, 4),
                'estimated' => ['total' => round($estimatedTotal, 4), 'materials' => round((float) ($estimatedByCategory['material'] ?? 0), 4),
                    'labor' => round((float) ($estimatedByCategory['labor'] ?? 0), 4), 'subcontracts' => round((float) ($estimatedByCategory['subcontract'] ?? 0), 4),
                    'equipment' => round((float) ($estimatedByCategory['equipment'] ?? 0), 4), 'overhead' => round((float) ($estimatedByCategory['overhead'] ?? 0), 4)],
                'actual' => array_map(fn ($value) => round($value, 4), $actual) + ['total' => round($actualTotal, 4)],
                'variance' => round($variance, 4), 'usage_percent' => round($usage, 2), 'status' => $status,
                'budget_margin' => round((float) $item->sales_total - $estimatedTotal, 4),
                'sale_less_actual' => round((float) $item->sales_total - $actualTotal, 4),
            ];
        })->values();

        $unlinked = [
            'materials' => $this->unlinkedCost($materials, $itemIds), 'labor' => $this->unlinkedCost($labor, $itemIds),
            'subcontracts' => $this->unlinkedCost($subcontracts, $itemIds), 'expenses' => $this->unlinkedCost($expenses, $itemIds),
        ];
        $unlinked['total'] = round(array_sum($unlinked), 4);
        $sales = (float) $rows->sum('sales');
        $estimatedTotal = (float) $rows->sum('estimated.total');
        $linkedActual = (float) $rows->sum('actual.total');
        $actualTotal = $linkedActual + $unlinked['total'];

        return [
            'period' => ['to' => $toDate], 'boq' => $boq, 'rows' => $rows, 'unlinked' => $unlinked,
            'summary' => ['sales' => round($sales, 4), 'estimated' => round($estimatedTotal, 4), 'actual' => round($actualTotal, 4),
                'linked_actual' => round($linkedActual, 4), 'remaining_budget' => round($estimatedTotal - $actualTotal, 4),
                'budget_margin' => round($sales - $estimatedTotal, 4), 'sale_less_actual' => round($sales - $actualTotal, 4),
                'usage_percent' => $estimatedTotal > 0 ? round(($actualTotal / $estimatedTotal) * 100, 2) : 0,
                'over_budget_items' => $rows->where('status', 'over')->count()],
            'warnings' => collect([
                $unlinked['total'] > 0 ? __('construction::lang.cost_profitability_warning_unlinked', ['amount' => CurrencyFormatter::format($unlinked['total'])]) : null,
                $estimatedTotal <= 0 ? __('construction::lang.cost_profitability_warning_no_budget') : null,
            ])->filter()->values(),
        ];
    }

    private function groupCosts(Collection $records): Collection
    {
        return $records->whereNotNull('boq_item_id')->groupBy('boq_item_id')->map(fn ($group) => round((float) $group->sum('amount'), 4));
    }

    private function unlinkedCost(Collection $records, Collection $itemIds): float
    {
        return round((float) $records->reject(fn ($record) => $record->boq_item_id && $itemIds->contains($record->boq_item_id))->sum('amount'), 4);
    }

    private function emptyReport(string $toDate): array
    {
        return ['period' => ['to' => $toDate], 'boq' => null, 'rows' => collect(),
            'unlinked' => ['materials' => 0.0, 'labor' => 0.0, 'subcontracts' => 0.0, 'expenses' => 0.0, 'total' => 0.0],
            'summary' => ['sales' => 0.0, 'estimated' => 0.0, 'actual' => 0.0, 'linked_actual' => 0.0, 'remaining_budget' => 0.0,
                'budget_margin' => 0.0, 'sale_less_actual' => 0.0, 'usage_percent' => 0.0, 'over_budget_items' => 0],
            'warnings' => collect([__('construction::lang.comparison_warning_no_boq')])];
    }
}
