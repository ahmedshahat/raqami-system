<?php

namespace Modules\Construction\Support;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;

/**
 * تحليل التكاليف الفعلية المعتمدة من المصادر الأربعة (خامات، عمالة، مقاولي باطن، مصروفات)
 * حسب المشروع والشهر، مع الأعمال المعتمدة للمالك لكل مشروع عشان النتيجة والهامش.
 */
class CostAnalytics
{
    public const SOURCES = ['materials', 'labor', 'subcontracts', 'expenses'];

    private const APPROVED_CUSTOMER_CERTIFICATE_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];

    private const MAX_MONTHS = 24;

    public function build(int $businessId, ?int $projectId, ?string $from, ?string $to): array
    {
        $rows = collect()
            ->concat($this->materialRows($businessId, $projectId, $from, $to))
            ->concat($this->laborRows($businessId, $projectId, $from, $to))
            ->concat($this->subcontractRows($businessId, $projectId, $from, $to))
            ->concat($this->expenseRows($businessId, $projectId, $from, $to));

        $sources = collect(self::SOURCES)->mapWithKeys(fn ($source) => [$source => round((float) $rows->where('source', $source)->sum('amount'), 4)]);
        $total = (float) $sources->sum();

        return [
            'sources' => $sources->all(),
            'total' => round($total, 4),
            'shares' => $sources->map(fn ($value) => $total > 0 ? round($value / $total * 100, 1) : 0)->all(),
            'months' => $this->months($rows, $from, $to),
            'projects' => $this->projects($rows, $businessId, $projectId, $from, $to),
        ];
    }

    private function materialRows(int $businessId, ?int $projectId, ?string $from, ?string $to): Collection
    {
        return ConstructionMaterialDocument::query()
            ->join('construction_material_document_lines as material_lines', 'material_lines.document_id', '=', 'construction_material_documents.id')
            ->where('construction_material_documents.business_id', $businessId)
            ->where('construction_material_documents.status', 'approved')
            ->when($projectId, fn ($query) => $query->where('construction_material_documents.project_id', $projectId))
            ->when($from, fn ($query) => $query->whereDate('construction_material_documents.document_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('construction_material_documents.document_date', '<=', $to))
            ->groupBy('construction_material_documents.project_id', DB::raw("DATE_FORMAT(construction_material_documents.document_date, '%Y-%m')"))
            ->get([
                'construction_material_documents.project_id',
                DB::raw("DATE_FORMAT(construction_material_documents.document_date, '%Y-%m') as month"),
                DB::raw("SUM(CASE WHEN construction_material_documents.type = 'return' THEN -material_lines.total_cost ELSE material_lines.total_cost END) as amount"),
            ])
            ->map(fn ($row) => $this->row('materials', $row));
    }

    private function laborRows(int $businessId, ?int $projectId, ?string $from, ?string $to): Collection
    {
        return ConstructionLaborSheet::query()
            ->join('construction_labor_sheet_lines as labor_lines', 'labor_lines.sheet_id', '=', 'construction_labor_sheets.id')
            ->where('construction_labor_sheets.business_id', $businessId)
            ->where('construction_labor_sheets.status', 'approved')
            ->when($projectId, fn ($query) => $query->where('construction_labor_sheets.project_id', $projectId))
            ->when($from, fn ($query) => $query->whereDate('construction_labor_sheets.period_end', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('construction_labor_sheets.period_end', '<=', $to))
            ->groupBy('construction_labor_sheets.project_id', DB::raw("DATE_FORMAT(construction_labor_sheets.period_end, '%Y-%m')"))
            ->get([
                'construction_labor_sheets.project_id',
                DB::raw("DATE_FORMAT(construction_labor_sheets.period_end, '%Y-%m') as month"),
                DB::raw('SUM(labor_lines.total_cost) as amount'),
            ])
            ->map(fn ($row) => $this->row('labor', $row));
    }

    private function subcontractRows(int $businessId, ?int $projectId, ?string $from, ?string $to): Collection
    {
        return ConstructionSubcontractCertificate::query()
            ->where('business_id', $businessId)
            ->where('status', 'approved')
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($from, fn ($query) => $query->whereDate('certificate_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('certificate_date', '<=', $to))
            ->groupBy('project_id', DB::raw("DATE_FORMAT(certificate_date, '%Y-%m')"))
            ->get([
                'project_id',
                DB::raw("DATE_FORMAT(certificate_date, '%Y-%m') as month"),
                DB::raw('SUM(gross_value) as amount'),
            ])
            ->map(fn ($row) => $this->row('subcontracts', $row));
    }

    private function expenseRows(int $businessId, ?int $projectId, ?string $from, ?string $to): Collection
    {
        return Transaction::query()
            ->where('business_id', $businessId)
            ->whereNotNull('construction_project_id')
            ->whereIn('type', ['expense', 'expense_refund'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('construction_expense_cancellations as expense_cancellations')
                ->whereColumn('expense_cancellations.expense_transaction_id', 'transactions.id'))
            ->when($projectId, fn ($query) => $query->where('construction_project_id', $projectId))
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->groupBy('construction_project_id', DB::raw("DATE_FORMAT(transaction_date, '%Y-%m')"))
            ->get([
                'construction_project_id as project_id',
                DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as month"),
                DB::raw("SUM(CASE WHEN type = 'expense_refund' THEN -final_total ELSE final_total END) as amount"),
            ])
            ->map(fn ($row) => $this->row('expenses', $row));
    }

    private function row(string $source, $row): array
    {
        return ['source' => $source, 'project_id' => (int) $row->project_id, 'month' => (string) $row->month, 'amount' => (float) $row->amount];
    }

    /** أعمدة الرسم الشهري: الفترة المختارة، أو آخر 12 شهر لو مفيش فترة. */
    private function months(Collection $rows, ?string $from, ?string $to): array
    {
        $end = ($to ? Carbon::parse($to) : now())->startOfMonth();
        $start = $from ? Carbon::parse($from)->startOfMonth() : $end->copy()->subMonths(11);
        if ($start->diffInMonths($end) >= self::MAX_MONTHS) {
            $start = $end->copy()->subMonths(self::MAX_MONTHS - 1);
        }

        $byMonth = $rows->groupBy('month');
        $months = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $monthRows = $byMonth->get($key, collect());
            $values = collect(self::SOURCES)->mapWithKeys(fn ($source) => [$source => round((float) $monthRows->where('source', $source)->sum('amount'), 4)]);
            $months[] = [
                'key' => $key,
                'date' => $cursor->copy(),
                'values' => $values->all(),
                'total' => round((float) $values->sum(), 4),
            ];
        }

        return $months;
    }

    /** تكلفة كل مشروع حسب المصدر، مع الأعمال المعتمدة للمالك والنتيجة والهامش. */
    private function projects(Collection $rows, int $businessId, ?int $projectId, ?string $from, ?string $to): array
    {
        $certified = ConstructionCustomerCertificate::query()
            ->where('business_id', $businessId)
            ->whereIn('status', self::APPROVED_CUSTOMER_CERTIFICATE_STATUSES)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($from, fn ($query) => $query->whereDate('certificate_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('certificate_date', '<=', $to))
            ->groupBy('project_id')
            ->selectRaw('project_id, SUM(current_approved_gross) as certified')
            ->get()
            ->pluck('certified', 'project_id');

        $projectIds = $rows->pluck('project_id')->merge($certified->keys())->unique()->filter();
        $projects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $projectIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return $projectIds
            ->filter(fn ($id) => $projects->has($id))
            ->map(function ($id) use ($rows, $certified, $projects) {
                $projectRows = $rows->where('project_id', $id);
                $values = collect(self::SOURCES)->mapWithKeys(fn ($source) => [$source => round((float) $projectRows->where('source', $source)->sum('amount'), 4)]);
                $cost = (float) $values->sum();
                $certifiedValue = (float) ($certified[$id] ?? 0);

                return [
                    'project' => $projects[$id],
                    'values' => $values->all(),
                    'total' => round($cost, 4),
                    'certified' => round($certifiedValue, 4),
                    'result' => round($certifiedValue - $cost, 4),
                    'margin' => $certifiedValue > 0 ? round(($certifiedValue - $cost) / $certifiedValue * 100, 1) : null,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
