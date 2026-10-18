<?php

namespace Modules\Construction\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionAccountingPosting;

class AccountingReconciliationReport
{
    public function build(int $businessId): array
    {
        $documents = collect();
        $this->appendLaborSheets($documents, $businessId);
        $this->appendMaterialDocuments($documents, $businessId);
        $this->appendSimple($documents, $businessId, 'construction_labor_payments', 'labor_payment', 'recorded', 'payment_date');
        $this->appendSimple($documents, $businessId, 'construction_subcontract_payments', 'subcontract_payment', 'recorded', 'payment_date');
        $this->appendSimple($documents, $businessId, 'construction_subcontract_retention_releases', 'subcontract_retention_release', 'recorded', 'release_date');
        $this->appendSimple($documents, $businessId, 'construction_customer_retention_releases', 'customer_retention_release', 'recorded', 'release_date');
        $this->appendSimple($documents, $businessId, 'construction_subcontract_advances', 'subcontract_advance', 'recorded', 'advance_date');
        $this->appendCertificates($documents, $businessId);
        $this->appendCustomerCollections($documents, $businessId);

        $issues = collect();
        foreach ($documents as $document) {
            $result = $this->checkPosting($businessId, $document);
            if ($result['status'] !== 'ok') {
                $issues->push($result);
            }
        }

        [$expenseChecked, $expenseIssues] = $this->checkProjectExpenses($businessId);
        $issues = $issues->concat($expenseIssues)->concat($this->orphanPostings($businessId));

        return [
            'checked' => $documents->count() + $expenseChecked,
            'issues' => $issues->values(),
            'healthy' => max(0, $documents->count() + $expenseChecked - $issues->whereNotIn('status', ['orphan_entry'])->count()),
            'counts' => $issues->groupBy('status')->map->count(),
        ];
    }

    private function appendLaborSheets(Collection $documents, int $businessId): void
    {
        DB::table('construction_labor_sheets as d')
            ->leftJoin('construction_labor_sheet_lines as l', 'l.sheet_id', '=', 'd.id')
            ->leftJoin('construction_projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.business_id', $businessId)->where('d.status', 'approved')
            ->groupBy('d.id', 'd.number', 'd.period_end', 'd.project_id', 'p.name')
            ->selectRaw("d.id, d.number, d.period_end as document_date, d.project_id, p.name as project_name, COALESCE(SUM(l.total_cost), 0) as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, 'labor_sheet', 'approval')));
    }

    private function appendMaterialDocuments(Collection $documents, int $businessId): void
    {
        DB::table('construction_material_documents as d')
            ->leftJoin('construction_material_document_lines as l', 'l.document_id', '=', 'd.id')
            ->leftJoin('construction_projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.business_id', $businessId)->where('d.status', 'approved')
            ->groupBy('d.id', 'd.number', 'd.document_date', 'd.project_id', 'd.type', 'p.name')
            ->selectRaw("d.id, d.number, d.document_date, d.project_id, d.type, p.name as project_name, COALESCE(SUM(l.total_cost), 0) as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, 'material_document', $row->type)));
    }

    private function appendSimple(Collection $documents, int $businessId, string $table, string $sourceType, string $event, string $dateColumn): void
    {
        DB::table($table.' as d')->leftJoin('construction_projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.business_id', $businessId)->where('d.status', 'recorded')
            ->selectRaw("d.id, d.number, d.{$dateColumn} as document_date, d.project_id, p.name as project_name, d.amount as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, $sourceType, $event)));
    }

    private function appendCertificates(Collection $documents, int $businessId): void
    {
        DB::table('construction_subcontract_certificates as d')->leftJoin('construction_projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.business_id', $businessId)->where('d.status', 'approved')
            ->selectRaw("d.id, d.number, d.certificate_date as document_date, d.project_id, p.name as project_name, (d.gross_value + d.tax_value) as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, 'subcontract_certificate', 'approval')));

        DB::table('construction_customer_certificates as d')->leftJoin('construction_projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.business_id', $businessId)->whereNotNull('d.invoice_transaction_id')->where('d.status', '!=', 'cancelled')
            ->selectRaw("d.id, d.number, d.certificate_date as document_date, d.project_id, p.name as project_name, (d.current_approved_gross + d.tax_value) as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, 'customer_certificate', 'invoiced')));
    }

    private function appendCustomerCollections(Collection $documents, int $businessId): void
    {
        DB::table('transaction_payments as d')
            ->join('transactions as t', 't.id', '=', 'd.transaction_id')
            ->join('construction_customer_certificates as c', 'c.invoice_transaction_id', '=', 't.id')
            ->leftJoin('construction_projects as p', 'p.id', '=', 'c.project_id')
            ->where('d.business_id', $businessId)
            ->where('c.business_id', $businessId)
            ->selectRaw("d.id, COALESCE(d.payment_ref_no, CONCAT('#', d.id)) as number, d.paid_on as document_date, c.project_id, p.name as project_name, d.amount as expected_amount")
            ->get()->each(fn ($row) => $documents->push($this->document($row, 'customer_collection', 'recorded')));
    }

    private function document(object $row, string $sourceType, string $event): array
    {
        return [
            'source_type' => $sourceType, 'source_id' => (int) $row->id, 'event' => $event,
            'number' => $row->number, 'date' => $row->document_date,
            'project_id' => (int) $row->project_id, 'project' => $row->project_name,
            'expected' => round((float) $row->expected_amount, 4),
        ];
    }

    private function checkPosting(int $businessId, array $document): array
    {
        $posting = ConstructionAccountingPosting::where('business_id', $businessId)
            ->where('source_type', $document['source_type'])->where('source_id', $document['source_id'])
            ->where('event', $document['event'])->first();
        if (! $posting) {
            return $document + ['status' => 'missing_entry', 'debit' => 0, 'credit' => 0, 'mapping_id' => null];
        }

        return $this->compareLines($document, $posting->accounting_mapping_id,
            DB::table('accounting_accounts_transactions')->where('acc_trans_mapping_id', $posting->accounting_mapping_id)->get());
    }

    private function compareLines(array $document, ?int $mappingId, Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return $document + ['status' => 'missing_entry', 'debit' => 0, 'credit' => 0, 'mapping_id' => $mappingId];
        }
        $debit = round((float) $lines->where('type', 'debit')->sum('amount'), 4);
        $credit = round((float) $lines->where('type', 'credit')->sum('amount'), 4);
        $status = abs($debit - $credit) > 0.0001 ? 'unbalanced_entry'
            : (abs($debit - $document['expected']) > 0.0001 ? 'amount_mismatch' : 'ok');

        return $document + compact('status', 'debit', 'credit') + ['mapping_id' => $mappingId];
    }

    private function checkProjectExpenses(int $businessId): array
    {
        $issues = collect();
        $expenses = DB::table('transactions as d')->leftJoin('construction_projects as p', 'p.id', '=', 'd.construction_project_id')
            ->leftJoin('construction_expense_cancellations as c', 'c.expense_transaction_id', '=', 'd.id')
            ->where('d.business_id', $businessId)->where('d.type', 'expense')->whereNotNull('d.construction_project_id')
            ->whereNull('c.id')->select('d.id', 'd.ref_no as number', 'd.transaction_date as document_date', 'd.construction_project_id as project_id', 'p.name as project_name', 'd.final_total as expected_amount')->get();
        foreach ($expenses as $expense) {
            $document = $this->document($expense, 'project_expense', 'recorded');
            $lines = DB::table('accounting_accounts_transactions')->where('transaction_id', $expense->id)->get();
            $result = $this->compareLines($document, null, $lines);
            if ($lines->isNotEmpty()) {
                $result['mapping_id'] = $lines->first()->acc_trans_mapping_id;
            }
            if ($result['status'] !== 'ok') {
                $issues->push($result);
            }
        }

        $cancellations = DB::table('construction_expense_cancellations as c')
            ->join('transactions as d', 'd.id', '=', 'c.expense_transaction_id')
            ->leftJoin('construction_projects as p', 'p.id', '=', 'c.project_id')
            ->where('c.business_id', $businessId)
            ->select('c.id', 'd.ref_no as number', 'c.cancelled_at as document_date', 'c.project_id', 'p.name as project_name', 'd.final_total as expected_amount', 'c.accounting_mapping_id')->get();
        foreach ($cancellations as $cancellation) {
            $document = $this->document($cancellation, 'project_expense_reversal', 'cancelled');
            $result = $this->compareLines($document, $cancellation->accounting_mapping_id,
                DB::table('accounting_accounts_transactions')->where('acc_trans_mapping_id', $cancellation->accounting_mapping_id)->get());
            if ($result['status'] !== 'ok') {
                $issues->push($result);
            }
        }

        return [$expenses->count() + $cancellations->count(), $issues];
    }

    private function orphanPostings(int $businessId): Collection
    {
        $tables = [
            'labor_sheet' => 'construction_labor_sheets', 'labor_payment' => 'construction_labor_payments',
            'material_document' => 'construction_material_documents', 'customer_certificate' => 'construction_customer_certificates',
            'subcontract_certificate' => 'construction_subcontract_certificates', 'subcontract_payment' => 'construction_subcontract_payments',
            'subcontract_retention_release' => 'construction_subcontract_retention_releases',
            'customer_retention_release' => 'construction_customer_retention_releases', 'subcontract_advance' => 'construction_subcontract_advances',
            'customer_collection' => 'transaction_payments',
        ];

        $postings = ConstructionAccountingPosting::where('business_id', $businessId)->get();

        return $postings->filter(function ($posting) use ($tables, $postings) {
            $table = $tables[$posting->source_type] ?? null;
            if ($table && DB::table($table)->where('id', $posting->source_id)->exists()) {
                return false;
            }

            // A deleted customer collection intentionally keeps both journal entries as an audit trail
            // after the payment row is removed. The pair is therefore valid, not an orphan.
            if ($posting->source_type === 'customer_collection') {
                $events = $postings->where('source_type', 'customer_collection')
                    ->where('source_id', $posting->source_id)->pluck('event');
                if ($events->contains('recorded') && $events->contains('deleted')) {
                    return false;
                }
            }

            return true;
        })->map(fn ($posting) => [
            'source_type' => $posting->source_type, 'source_id' => $posting->source_id, 'event' => $posting->event,
            'number' => '#'.$posting->source_id, 'date' => optional($posting->posted_at)->toDateString(),
            'project_id' => null, 'project' => '—', 'expected' => 0, 'debit' => 0, 'credit' => 0,
            'mapping_id' => $posting->accounting_mapping_id, 'status' => 'orphan_entry',
        ]);
    }
}
