<?php

namespace Modules\Construction\Support;

use App\BusinessLocation;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Construction\Entities\ConstructionAccountingPosting;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionCustomerRetentionRelease;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionLaborPayment;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractAdvance;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;

class ConstructionAccountingPoster
{
    public function reversePostedDocument(
        int $businessId,
        string $sourceType,
        int $sourceId,
        string $originalEvent,
        string $reference,
        string $note,
        $operationDate = null
    ): ConstructionAccountingPosting {
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $businessId, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'event' => 'cancelled',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $original = ConstructionAccountingPosting::where([
            'business_id' => $businessId, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'event' => $originalEvent,
        ])->with('mapping')->lockForUpdate()->first();
        if (! $original?->mapping) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.original_accounting_entry_not_found'),
            ]);
        }

        $originalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $original->accounting_mapping_id)->get();
        if ($originalLines->isEmpty()) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.original_accounting_entry_not_found'),
            ]);
        }

        $userId = auth()->id() ?: $original->posted_by;
        $operationDate = \Carbon\Carbon::parse($operationDate ?: now())->startOfDay();
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $businessId;
        $mapping->construction_project_id = $original->mapping->construction_project_id;
        $mapping->ref_no = $reference;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $original->mapping->party_name;
        $mapping->payment_method = $original->mapping->payment_method;
        $mapping->payment_reference = $original->mapping->payment_reference;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        foreach ($originalLines as $line) {
            AccountingAccountsTransaction::create([
                'accounting_account_id' => $line->accounting_account_id,
                'construction_project_id' => $line->construction_project_id,
                'acc_trans_mapping_id' => $mapping->id,
                'amount' => $line->amount,
                'type' => $line->type === 'debit' ? 'credit' : 'debit',
                'sub_type' => 'journal_entry',
                'map_type' => $line->map_type.'_reversal',
                'created_by' => $userId,
                'operation_date' => $operationDate,
                'note' => $note,
            ]);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $businessId, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'event' => 'cancelled',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId, 'posted_at' => now(),
        ]);
    }

    public function postLaborSheetApproval(ConstructionLaborSheet $sheet): ?ConstructionAccountingPosting
    {
        $settings = ConstructionAccountingSetting::where('business_id', $sheet->business_id)->first();
        if (! $settings) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $sheet->business_id, 'source_type' => 'labor_sheet',
            'source_id' => $sheet->id, 'event' => 'approval',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $this->validateAccounts($sheet->business_id, [
            (int) $settings->labor_cost_account_id,
            (int) $settings->labor_payable_account_id,
        ], 'accounting', 'construction::lang.labor_accounting_accounts_incomplete');

        $amount = $sheet->totalCost();
        if ($amount <= 0.0001) {
            throw ValidationException::withMessages(['accounting' => __('construction::lang.labor_cost_must_be_positive')]);
        }
        $sheet->loadMissing('project');

        return $this->createLaborSheetPosting(
            $sheet, 'approval', $sheet->period_end,
            (int) $settings->labor_cost_account_id,
            (int) $settings->labor_payable_account_id,
            'CT-'.$sheet->number,
            __('construction::lang.labor_sheet_journal_note', ['number' => $sheet->number, 'project' => $sheet->project?->name])
        );
    }

    public function reverseLaborSheetApproval(ConstructionLaborSheet $sheet): ?ConstructionAccountingPosting
    {
        $settings = ConstructionAccountingSetting::where('business_id', $sheet->business_id)->first();
        if (! $settings) {
            return null;
        }
        $original = ConstructionAccountingPosting::where([
            'business_id' => $sheet->business_id, 'source_type' => 'labor_sheet',
            'source_id' => $sheet->id, 'event' => 'approval',
        ])->first();
        if (! $original) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $sheet->business_id, 'source_type' => 'labor_sheet',
            'source_id' => $sheet->id, 'event' => 'cancelled',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $this->validateAccounts($sheet->business_id, [
            (int) $settings->labor_cost_account_id,
            (int) $settings->labor_payable_account_id,
        ], 'accounting', 'construction::lang.labor_accounting_accounts_incomplete');

        return $this->createLaborSheetPosting(
            $sheet, 'cancelled', $sheet->cancelled_at ?: now(),
            (int) $settings->labor_payable_account_id,
            (int) $settings->labor_cost_account_id,
            'CT-LAB-CAN-'.$sheet->number,
            __('construction::lang.labor_sheet_reversal_journal_note', ['number' => $sheet->number])
        );
    }

    public function postLaborPayment(ConstructionLaborPayment $payment): ?ConstructionAccountingPosting
    {
        $settings = ConstructionAccountingSetting::where('business_id', $payment->business_id)->first();
        if (! $settings) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $payment->business_id, 'source_type' => 'labor_payment',
            'source_id' => $payment->id, 'event' => 'recorded',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $this->validateAccounts($payment->business_id, [
            (int) $settings->labor_payable_account_id,
            (int) $payment->accounting_account_id,
        ], 'accounting_account_id', 'construction::lang.labor_payment_accounts_incomplete');

        $payment->loadMissing('sheet.project');
        $userId = auth()->id() ?: $payment->created_by;
        $operationDate = $payment->payment_date->copy()->startOfDay();
        $note = __('construction::lang.labor_payment_journal_note', [
            'payment' => $payment->number,
            'sheet' => $payment->sheet->number,
        ]);
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $payment->business_id;
        $mapping->ref_no = 'CT-'.$payment->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $payment->sheet?->project?->name;
        $mapping->payment_method = $payment->method;
        $mapping->payment_reference = $payment->reference_no;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = round((float) $payment->amount, 4);
        $this->createAccountingLine($mapping->id, (int) $settings->labor_payable_account_id, $amount, 'debit', 'construction_labor_payable_payment', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, (int) $payment->accounting_account_id, $amount, 'credit', 'construction_labor_cash_payment', $operationDate, $note, $userId);

        return ConstructionAccountingPosting::create([
            'business_id' => $payment->business_id,
            'source_type' => 'labor_payment',
            'source_id' => $payment->id,
            'event' => 'recorded',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }

    public function postMaterialDocumentApproval(
        ConstructionMaterialDocument $document
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $document->business_id)->first();
        if (! $settings) {
            return null;
        }

        $event = $document->type === 'return' ? 'return' : 'issue';
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $document->business_id,
            'source_type' => 'material_document',
            'source_id' => $document->id,
            'event' => $event,
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $inventoryAccountId = (int) $settings->inventory_account_id;
        $materialCostAccountId = (int) $settings->material_cost_account_id;
        $this->validateAccounts(
            $document->business_id,
            [$inventoryAccountId, $materialCostAccountId],
            'accounting',
            'construction::lang.material_accounting_accounts_incomplete'
        );

        $amount = round((float) $document->lines()->sum('total_cost'), 4);
        if ($amount <= 0.0001) {
            return null;
        }

        $document->loadMissing('project');
        $userId = auth()->id() ?: $document->approved_by ?: $document->created_by;
        $operationDate = $document->document_date->copy()->startOfDay();
        $note = __(
            $event === 'return'
                ? 'construction::lang.material_return_journal_note'
                : 'construction::lang.material_issue_journal_note',
            ['number' => $document->number, 'project' => $document->project?->name]
        );

        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $document->business_id;
        $mapping->ref_no = 'CT-'.$document->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $document->project?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        if ($event === 'return') {
            $this->createAccountingLine($mapping->id, $inventoryAccountId, $amount, 'debit', 'construction_material_return_inventory', $operationDate, $note, $userId);
            $this->createAccountingLine($mapping->id, $materialCostAccountId, $amount, 'credit', 'construction_material_return_cost', $operationDate, $note, $userId);
        } else {
            $this->createAccountingLine($mapping->id, $materialCostAccountId, $amount, 'debit', 'construction_material_issue_cost', $operationDate, $note, $userId);
            $this->createAccountingLine($mapping->id, $inventoryAccountId, $amount, 'credit', 'construction_material_issue_inventory', $operationDate, $note, $userId);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $document->business_id,
            'source_type' => 'material_document',
            'source_id' => $document->id,
            'event' => $event,
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }

    public function syncCustomerCollection(TransactionPayment $payment): ?ConstructionAccountingPosting
    {
        $transaction = Transaction::where('business_id', $payment->business_id)
            ->where('type', 'sell')->where('status', 'final')
            ->where('source', 'construction_certificate')
            ->find($payment->transaction_id);
        if (! $transaction) {
            return null;
        }

        $certificate = ConstructionCustomerCertificate::where('business_id', $payment->business_id)
            ->where('invoice_transaction_id', $transaction->id)->first();
        $settings = ConstructionAccountingSetting::where('business_id', $payment->business_id)->first();
        if (! $certificate || ! $settings) {
            return null;
        }

        $debitAccountId = $payment->method === 'advance'
            ? (int) $settings->customer_advance_account_id
            : $this->customerCollectionCashAccount($transaction, $settings);
        $receivableAccountId = (int) $settings->customer_receivable_account_id;
        $this->validateAccounts(
            $payment->business_id,
            [$debitAccountId, $receivableAccountId],
            'accounting',
            'construction::lang.customer_collection_accounts_incomplete'
        );

        $amount = round((float) $payment->amount, 4);
        if ($amount <= 0.0001) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.customer_collection_amount_invalid'),
            ]);
        }

        $userId = auth()->id() ?: $payment->created_by;
        $operationDate = \Carbon\Carbon::parse($payment->paid_on ?: now())->startOfDay();
        $note = __('construction::lang.customer_collection_journal_note', [
            'payment' => $payment->payment_ref_no ?: $payment->id,
            'certificate' => $certificate->number,
        ]);
        $posting = ConstructionAccountingPosting::where([
            'business_id' => $payment->business_id,
            'source_type' => 'customer_collection',
            'source_id' => $payment->id,
            'event' => 'recorded',
        ])->lockForUpdate()->first();

        if ($posting) {
            $mapping = $posting->mapping()->lockForUpdate()->firstOrFail();
            AccountingAccountsTransaction::where('acc_trans_mapping_id', $mapping->id)->delete();
        } else {
            $mapping = new AccountingAccTransMapping();
            $mapping->business_id = $payment->business_id;
            $mapping->ref_no = 'CT-COL-'.($payment->payment_ref_no ?: $payment->id);
            $mapping->type = 'journal_entry';
            $mapping->created_by = $userId;
        }

        $certificate->loadMissing('project.customer');
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $certificate->project?->customer?->supplier_business_name
            ?: $certificate->project?->customer?->name;
        $mapping->payment_method = $payment->method;
        $mapping->payment_reference = $payment->payment_ref_no;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $this->createAccountingLine($mapping->id, $debitAccountId, $amount, 'debit', 'construction_customer_collection_cash', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, $receivableAccountId, $amount, 'credit', 'construction_customer_collection_receivable', $operationDate, $note, $userId);

        if ($posting) {
            $posting->update(['posted_by' => $userId, 'posted_at' => now()]);
            return $posting->fresh();
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $payment->business_id,
            'source_type' => 'customer_collection',
            'source_id' => $payment->id,
            'event' => 'recorded',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }

    public function reverseCustomerCollection(TransactionPayment $payment): ?ConstructionAccountingPosting
    {
        $original = ConstructionAccountingPosting::where([
            'business_id' => $payment->business_id,
            'source_type' => 'customer_collection',
            'source_id' => $payment->id,
            'event' => 'recorded',
        ])->with('mapping')->first();
        if (! $original?->mapping) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $payment->business_id,
            'source_type' => 'customer_collection',
            'source_id' => $payment->id,
            'event' => 'deleted',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $originalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $original->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $cashLine = $originalLines->get('construction_customer_collection_cash');
        $receivableLine = $originalLines->get('construction_customer_collection_receivable');
        if (! $cashLine || ! $receivableLine) {
            return null;
        }

        $userId = auth()->id() ?: $payment->created_by;
        $operationDate = now()->startOfDay();
        $note = __('construction::lang.customer_collection_reversal_journal_note', [
            'payment' => $payment->payment_ref_no ?: $payment->id,
        ]);
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $payment->business_id;
        $mapping->ref_no = 'CT-COL-CAN-'.($payment->payment_ref_no ?: $payment->id);
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $original->mapping->party_name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = round((float) $receivableLine->amount, 4);
        $this->createAccountingLine($mapping->id, (int) $receivableLine->accounting_account_id, $amount, 'debit', 'construction_customer_collection_reversal_receivable', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, (int) $cashLine->accounting_account_id, $amount, 'credit', 'construction_customer_collection_reversal_cash', $operationDate, $note, $userId);

        return ConstructionAccountingPosting::create([
            'business_id' => $payment->business_id,
            'source_type' => 'customer_collection',
            'source_id' => $payment->id,
            'event' => 'deleted',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }

    public function postCustomerCertificateInvoice(
        ConstructionCustomerCertificate $certificate
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $certificate->business_id)->first();
        if (! $settings) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $certificate->business_id,
            'source_type' => 'customer_certificate',
            'source_id' => $certificate->id,
            'event' => 'invoiced',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $amounts = [
            'receivable' => round((float) $certificate->net_due, 4),
            'retention' => round((float) $certificate->retention_value, 4),
            'advance' => round((float) $certificate->advance_recovery_value, 4),
            'deduction' => round((float) $certificate->other_deductions_value, 4),
            'revenue' => round((float) $certificate->current_approved_gross, 4),
            'tax' => round((float) $certificate->tax_value, 4),
        ];
        if (abs(array_sum(array_intersect_key($amounts, array_flip(['receivable', 'retention', 'advance', 'deduction'])))
            - ($amounts['revenue'] + $amounts['tax'])) > 0.0001) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.customer_certificate_unbalanced'),
            ]);
        }

        $accountMap = [
            'receivable' => 'customer_receivable_account_id',
            'retention' => 'customer_retention_account_id',
            'advance' => 'customer_advance_account_id',
            'deduction' => 'customer_deduction_account_id',
            'revenue' => 'construction_revenue_account_id',
            'tax' => 'sales_tax_payable_account_id',
        ];
        $requiredAccountIds = collect($accountMap)
            ->filter(fn ($field, $amountKey) => $amounts[$amountKey] > 0.0001)
            ->map(fn ($field) => (int) $settings->{$field});
        if ($requiredAccountIds->contains(0) || AccountingAccount::where('business_id', $certificate->business_id)
            ->where('status', 'active')
            ->whereIn('id', $requiredAccountIds->unique()->all())
            ->count() !== $requiredAccountIds->unique()->count()) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.customer_certificate_accounts_incomplete'),
            ]);
        }

        $certificate->loadMissing(['project.customer', 'invoice']);
        $operationDate = \Carbon\Carbon::parse(
            $certificate->invoice?->transaction_date ?: $certificate->certificate_date
        )->startOfDay();
        $note = __('construction::lang.customer_certificate_journal_note', ['number' => $certificate->number]);
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $certificate->business_id;
        $mapping->ref_no = 'CT-CUS-'.$certificate->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = auth()->id();
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $certificate->project?->customer?->supplier_business_name
            ?: $certificate->project?->customer?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        foreach ([
            ['amount' => 'receivable', 'account' => 'customer_receivable_account_id', 'type' => 'debit', 'map' => 'construction_customer_receivable'],
            ['amount' => 'retention', 'account' => 'customer_retention_account_id', 'type' => 'debit', 'map' => 'construction_customer_retention'],
            ['amount' => 'advance', 'account' => 'customer_advance_account_id', 'type' => 'debit', 'map' => 'construction_customer_advance_recovery'],
            ['amount' => 'deduction', 'account' => 'customer_deduction_account_id', 'type' => 'debit', 'map' => 'construction_customer_deduction'],
            ['amount' => 'revenue', 'account' => 'construction_revenue_account_id', 'type' => 'credit', 'map' => 'construction_customer_revenue'],
            ['amount' => 'tax', 'account' => 'sales_tax_payable_account_id', 'type' => 'credit', 'map' => 'construction_customer_tax'],
        ] as $line) {
            $amount = $amounts[$line['amount']];
            if ($amount <= 0.0001) {
                continue;
            }
            AccountingAccountsTransaction::create([
                'accounting_account_id' => $settings->{$line['account']},
                'acc_trans_mapping_id' => $mapping->id,
                'amount' => $amount,
                'type' => $line['type'],
                'sub_type' => 'journal_entry',
                'map_type' => $line['map'],
                'created_by' => auth()->id(),
                'operation_date' => $operationDate,
                'note' => $note,
            ]);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $certificate->business_id,
            'source_type' => 'customer_certificate',
            'source_id' => $certificate->id,
            'event' => 'invoiced',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    public function postSubcontractCertificateApproval(
        ConstructionSubcontractCertificate $certificate
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $certificate->business_id)->first();

        // Accounting remains optional until the business explicitly completes Construction setup.
        if (! $settings) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $certificate->business_id,
            'source_type' => 'subcontract_certificate',
            'source_id' => $certificate->id,
            'event' => 'approval',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $accountFields = [
            'subcontract_cost_account_id',
            'subcontract_payable_account_id',
            'subcontract_retention_account_id',
            'subcontract_deduction_account_id',
        ];
        if ((float) $certificate->tax_value > 0.0001) {
            $accountFields[] = 'input_tax_account_id';
        }
        if ((float) $certificate->advance_recovery_value > 0.0001) {
            $accountFields[] = 'subcontract_advance_account_id';
        }
        $accountIds = collect($accountFields)->map(fn ($field) => (int) $settings->{$field});
        if ($accountIds->contains(0) || AccountingAccount::where('business_id', $certificate->business_id)
            ->where('status', 'active')
            ->whereIn('id', $accountIds->unique()->all())
            ->count() !== $accountIds->unique()->count()) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.construction_accounting_accounts_incomplete'),
            ]);
        }

        $gross = round((float) $certificate->gross_value, 4);
        $net = round((float) $certificate->net_value, 4);
        $retention = round((float) $certificate->retention_value, 4);
        $deductions = round((float) $certificate->other_deductions, 4);
        $tax = round((float) $certificate->tax_value, 4);
        $advanceRecovery = round((float) $certificate->advance_recovery_value, 4);
        if (abs(($gross + $tax) - ($net + $retention + $deductions + $advanceRecovery)) > 0.0001) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.subcontract_certificate_unbalanced'),
            ]);
        }

        $certificate->loadMissing('subcontract.subcontractor');
        $operationDate = $certificate->certificate_date->copy()->startOfDay();
        $note = __('construction::lang.subcontract_certificate_journal_note', [
            'number' => $certificate->number,
        ]);

        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $certificate->business_id;
        $mapping->ref_no = 'CT-SUB-'.$certificate->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = auth()->id();
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $certificate->subcontract?->subcontractor?->supplier_business_name
            ?: $certificate->subcontract?->subcontractor?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $lines = [
            ['account' => $settings->subcontract_cost_account_id, 'amount' => $gross, 'type' => 'debit', 'map' => 'construction_subcontract_cost'],
            ['account' => $settings->input_tax_account_id, 'amount' => $tax, 'type' => 'debit', 'map' => 'construction_subcontract_input_tax'],
            ['account' => $settings->subcontract_payable_account_id, 'amount' => $net, 'type' => 'credit', 'map' => 'construction_subcontract_payable'],
            ['account' => $settings->subcontract_retention_account_id, 'amount' => $retention, 'type' => 'credit', 'map' => 'construction_subcontract_retention'],
            ['account' => $settings->subcontract_deduction_account_id, 'amount' => $deductions, 'type' => 'credit', 'map' => 'construction_subcontract_deduction'],
            ['account' => $settings->subcontract_advance_account_id, 'amount' => $advanceRecovery, 'type' => 'credit', 'map' => 'construction_subcontract_advance_recovery'],
        ];
        foreach ($lines as $line) {
            if ($line['amount'] <= 0.0001) {
                continue;
            }
            AccountingAccountsTransaction::create([
                'accounting_account_id' => $line['account'],
                'acc_trans_mapping_id' => $mapping->id,
                'amount' => $line['amount'],
                'type' => $line['type'],
                'sub_type' => 'journal_entry',
                'map_type' => $line['map'],
                'created_by' => auth()->id(),
                'operation_date' => $operationDate,
                'note' => $note,
            ]);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $certificate->business_id,
            'source_type' => 'subcontract_certificate',
            'source_id' => $certificate->id,
            'event' => 'approval',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    public function postSubcontractAdvance(ConstructionSubcontractAdvance $advance): ?ConstructionAccountingPosting
    {
        $settings = ConstructionAccountingSetting::where('business_id', $advance->business_id)->first();
        if (! $settings) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $advance->business_id, 'source_type' => 'subcontract_advance',
            'source_id' => $advance->id, 'event' => 'recorded',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        $this->validateAccounts($advance->business_id, [
            (int) $settings->subcontract_advance_account_id,
            (int) $advance->accounting_account_id,
        ], 'accounting_account_id', 'construction::lang.subcontract_advance_accounts_incomplete');

        $advance->loadMissing('subcontract.subcontractor');
        $userId = auth()->id() ?: $advance->created_by;
        $operationDate = $advance->advance_date->copy()->startOfDay();
        $note = __('construction::lang.subcontract_advance_journal_note', [
            'advance' => $advance->number, 'subcontract' => $advance->subcontract?->number,
        ]);
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $advance->business_id;
        $mapping->ref_no = 'CT-ADV-'.$advance->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $advance->subcontract?->subcontractor?->supplier_business_name
            ?: $advance->subcontract?->subcontractor?->name;
        $mapping->payment_method = $advance->method;
        $mapping->payment_reference = $advance->reference_no;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = round((float) $advance->amount, 4);
        $this->createAccountingLine($mapping->id, (int) $settings->subcontract_advance_account_id, $amount, 'debit', 'construction_subcontract_advance', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, (int) $advance->accounting_account_id, $amount, 'credit', 'construction_subcontract_advance_cash', $operationDate, $note, $userId);

        return ConstructionAccountingPosting::create([
            'business_id' => $advance->business_id, 'source_type' => 'subcontract_advance',
            'source_id' => $advance->id, 'event' => 'recorded',
            'accounting_mapping_id' => $mapping->id, 'posted_by' => $userId, 'posted_at' => now(),
        ]);
    }

    public function postCustomerRetentionRelease(
        ConstructionCustomerRetentionRelease $release
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $release->business_id)->first();
        if (! $settings) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id, 'source_type' => 'customer_retention_release',
            'source_id' => $release->id, 'event' => 'recorded',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        $this->validateAccounts($release->business_id, [
            (int) $settings->customer_receivable_account_id,
            (int) $settings->customer_retention_account_id,
        ], 'accounting', 'construction::lang.customer_retention_accounts_incomplete');

        return $this->createCustomerRetentionPosting(
            $release, 'recorded', $release->release_date,
            (int) $settings->customer_receivable_account_id,
            (int) $settings->customer_retention_account_id,
            'CT-CUS-RET-'.$release->number,
            __('construction::lang.customer_retention_release_journal_note', ['release' => $release->number])
        );
    }

    public function reverseCustomerRetentionRelease(
        ConstructionCustomerRetentionRelease $release
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $release->business_id)->first();
        if (! $settings) {
            return null;
        }
        $original = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id, 'source_type' => 'customer_retention_release',
            'source_id' => $release->id, 'event' => 'recorded',
        ])->first();
        if (! $original) {
            return null;
        }
        $existing = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id, 'source_type' => 'customer_retention_release',
            'source_id' => $release->id, 'event' => 'cancelled',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        $this->validateAccounts($release->business_id, [
            (int) $settings->customer_receivable_account_id,
            (int) $settings->customer_retention_account_id,
        ], 'accounting', 'construction::lang.customer_retention_accounts_incomplete');

        return $this->createCustomerRetentionPosting(
            $release, 'cancelled', $release->cancelled_at ?: now(),
            (int) $settings->customer_retention_account_id,
            (int) $settings->customer_receivable_account_id,
            'CT-CUS-RET-CAN-'.$release->number,
            __('construction::lang.customer_retention_release_reversal_journal_note', ['release' => $release->number])
        );
    }

    public function postSubcontractPayment(
        ConstructionSubcontractPayment $payment
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $payment->business_id)->first();
        if (! $settings) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $payment->business_id,
            'source_type' => 'subcontract_payment',
            'source_id' => $payment->id,
            'event' => 'recorded',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $accountIds = collect([
            (int) $settings->subcontract_payable_account_id,
            (int) $settings->subcontract_retention_account_id,
            (int) $payment->accounting_account_id,
        ]);
        if ($accountIds->contains(0) || AccountingAccount::where('business_id', $payment->business_id)
            ->where('status', 'active')
            ->whereIn('id', $accountIds->unique()->all())
            ->count() !== $accountIds->unique()->count()) {
            throw ValidationException::withMessages([
                'accounting_account_id' => __('construction::lang.construction_payment_accounts_incomplete'),
            ]);
        }

        $payment->loadMissing(['certificate.activeRetentionReleases', 'subcontract.subcontractor']);
        foreach ($payment->certificate->activeRetentionReleases as $release) {
            $this->postSubcontractRetentionRelease($release);
        }
        $amount = round((float) $payment->amount, 4);

        $operationDate = $payment->payment_date->copy()->startOfDay();
        $note = __('construction::lang.subcontract_payment_journal_note', [
            'payment' => $payment->number,
            'certificate' => $payment->certificate->number,
        ]);

        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $payment->business_id;
        $mapping->ref_no = 'CT-PAY-'.$payment->number;
        $mapping->type = 'journal_entry';
        $mapping->created_by = auth()->id();
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $payment->subcontract?->subcontractor?->supplier_business_name
            ?: $payment->subcontract?->subcontractor?->name;
        $mapping->payment_method = $payment->method;
        $mapping->payment_reference = $payment->reference_no;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $lines = [
            ['account' => $settings->subcontract_payable_account_id, 'amount' => $amount, 'type' => 'debit', 'map' => 'construction_subcontract_payable_payment'],
            ['account' => $payment->accounting_account_id, 'amount' => $amount, 'type' => 'credit', 'map' => 'construction_subcontract_cash_payment'],
        ];
        foreach ($lines as $line) {
            if ($line['amount'] <= 0.0001) {
                continue;
            }
            AccountingAccountsTransaction::create([
                'accounting_account_id' => $line['account'],
                'acc_trans_mapping_id' => $mapping->id,
                'amount' => $line['amount'],
                'type' => $line['type'],
                'sub_type' => 'journal_entry',
                'map_type' => $line['map'],
                'created_by' => auth()->id(),
                'operation_date' => $operationDate,
                'note' => $note,
            ]);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $payment->business_id,
            'source_type' => 'subcontract_payment',
            'source_id' => $payment->id,
            'event' => 'recorded',
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    public function postSubcontractRetentionRelease(
        ConstructionSubcontractRetentionRelease $release
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $release->business_id)->first();
        if (! $settings) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id,
            'source_type' => 'subcontract_retention_release',
            'source_id' => $release->id,
            'event' => 'recorded',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $this->validateSubcontractLiabilityAccounts($settings, $release->business_id);
        $release->loadMissing(['certificate', 'subcontract.subcontractor']);

        return $this->createRetentionReleasePosting(
            $release,
            'recorded',
            $release->release_date->copy()->startOfDay(),
            $settings->subcontract_retention_account_id,
            $settings->subcontract_payable_account_id,
            'CT-RET-'.$release->number,
            __('construction::lang.retention_release_journal_note', [
                'release' => $release->number,
                'certificate' => $release->certificate->number,
            ])
        );
    }

    public function reverseSubcontractRetentionRelease(
        ConstructionSubcontractRetentionRelease $release
    ): ?ConstructionAccountingPosting {
        $settings = ConstructionAccountingSetting::where('business_id', $release->business_id)->first();
        if (! $settings) {
            return null;
        }

        $original = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id,
            'source_type' => 'subcontract_retention_release',
            'source_id' => $release->id,
            'event' => 'recorded',
        ])->first();
        if (! $original) {
            return null;
        }

        $existing = ConstructionAccountingPosting::where([
            'business_id' => $release->business_id,
            'source_type' => 'subcontract_retention_release',
            'source_id' => $release->id,
            'event' => 'cancelled',
        ])->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $this->validateSubcontractLiabilityAccounts($settings, $release->business_id);
        $release->loadMissing(['certificate', 'subcontract.subcontractor']);

        return $this->createRetentionReleasePosting(
            $release,
            'cancelled',
            ($release->cancelled_at ?: now())->copy()->startOfDay(),
            $settings->subcontract_payable_account_id,
            $settings->subcontract_retention_account_id,
            'CT-RET-CAN-'.$release->number,
            __('construction::lang.retention_release_reversal_journal_note', [
                'release' => $release->number,
                'certificate' => $release->certificate->number,
            ])
        );
    }

    private function validateSubcontractLiabilityAccounts(
        ConstructionAccountingSetting $settings,
        int $businessId
    ): void {
        $accountIds = collect([
            (int) $settings->subcontract_payable_account_id,
            (int) $settings->subcontract_retention_account_id,
        ]);
        if ($accountIds->contains(0) || AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('id', $accountIds->unique()->all())
            ->count() !== $accountIds->unique()->count()) {
            throw ValidationException::withMessages([
                'accounting' => __('construction::lang.construction_retention_accounts_incomplete'),
            ]);
        }
    }

    private function customerCollectionCashAccount(
        Transaction $transaction,
        ConstructionAccountingSetting $settings
    ): int {
        $location = BusinessLocation::where('business_id', $transaction->business_id)->find($transaction->location_id);
        $map = $location ? json_decode($location->accounting_default_map ?: '[]', true) : [];
        $mappedAccountId = (int) ($map['sell_payment']['deposit_to'] ?? 0);

        return $mappedAccountId ?: (int) $settings->cash_account_id;
    }

    private function validateAccounts(int $businessId, array $accountIds, string $errorField, string $messageKey): void
    {
        $accountIds = collect($accountIds);
        if ($accountIds->contains(0) || AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')->whereIn('id', $accountIds->unique()->all())
            ->count() !== $accountIds->unique()->count()) {
            throw ValidationException::withMessages([$errorField => __($messageKey)]);
        }
    }

    private function createAccountingLine(
        int $mappingId,
        int $accountId,
        float $amount,
        string $type,
        string $mapType,
        $operationDate,
        string $note,
        ?int $userId
    ): void {
        AccountingAccountsTransaction::create([
            'accounting_account_id' => $accountId,
            'acc_trans_mapping_id' => $mappingId,
            'amount' => $amount,
            'type' => $type,
            'sub_type' => 'journal_entry',
            'map_type' => $mapType,
            'created_by' => $userId,
            'operation_date' => $operationDate,
            'note' => $note,
        ]);
    }

    private function createRetentionReleasePosting(
        ConstructionSubcontractRetentionRelease $release,
        string $event,
        $operationDate,
        int $debitAccountId,
        int $creditAccountId,
        string $reference,
        string $note
    ): ConstructionAccountingPosting {
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $release->business_id;
        $mapping->ref_no = $reference;
        $mapping->type = 'journal_entry';
        $mapping->created_by = auth()->id();
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $release->subcontract?->subcontractor?->supplier_business_name
            ?: $release->subcontract?->subcontractor?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = round((float) $release->amount, 4);
        foreach ([
            ['account' => $debitAccountId, 'type' => 'debit', 'map' => 'construction_retention_'.$event.'_debit'],
            ['account' => $creditAccountId, 'type' => 'credit', 'map' => 'construction_retention_'.$event.'_credit'],
        ] as $line) {
            AccountingAccountsTransaction::create([
                'accounting_account_id' => $line['account'],
                'acc_trans_mapping_id' => $mapping->id,
                'amount' => $amount,
                'type' => $line['type'],
                'sub_type' => 'journal_entry',
                'map_type' => $line['map'],
                'created_by' => auth()->id(),
                'operation_date' => $operationDate,
                'note' => $note,
            ]);
        }

        return ConstructionAccountingPosting::create([
            'business_id' => $release->business_id,
            'source_type' => 'subcontract_retention_release',
            'source_id' => $release->id,
            'event' => $event,
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    private function createCustomerRetentionPosting(
        ConstructionCustomerRetentionRelease $release,
        string $event,
        $operationDate,
        int $debitAccountId,
        int $creditAccountId,
        string $reference,
        string $note
    ): ConstructionAccountingPosting {
        $release->loadMissing('project.customer');
        $userId = auth()->id() ?: $release->created_by;
        $operationDate = \Carbon\Carbon::parse($operationDate)->startOfDay();
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $release->business_id;
        $mapping->ref_no = $reference;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $release->project?->customer?->supplier_business_name
            ?: $release->project?->customer?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = round((float) $release->amount, 4);
        $this->createAccountingLine($mapping->id, $debitAccountId, $amount, 'debit', 'construction_customer_retention_'.$event.'_debit', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, $creditAccountId, $amount, 'credit', 'construction_customer_retention_'.$event.'_credit', $operationDate, $note, $userId);

        return ConstructionAccountingPosting::create([
            'business_id' => $release->business_id,
            'source_type' => 'customer_retention_release',
            'source_id' => $release->id,
            'event' => $event,
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }

    private function createLaborSheetPosting(
        ConstructionLaborSheet $sheet,
        string $event,
        $operationDate,
        int $debitAccountId,
        int $creditAccountId,
        string $reference,
        string $note
    ): ConstructionAccountingPosting {
        $sheet->loadMissing('project');
        $userId = auth()->id() ?: $sheet->approved_by ?: $sheet->created_by;
        $operationDate = \Carbon\Carbon::parse($operationDate)->startOfDay();
        $mapping = new AccountingAccTransMapping();
        $mapping->business_id = $sheet->business_id;
        $mapping->ref_no = $reference;
        $mapping->type = 'journal_entry';
        $mapping->created_by = $userId;
        $mapping->operation_date = $operationDate;
        $mapping->party_name = $sheet->project?->name;
        $mapping->status = 'active';
        $mapping->note = $note;
        $mapping->save();

        $amount = $sheet->totalCost();
        $this->createAccountingLine($mapping->id, $debitAccountId, $amount, 'debit', 'construction_labor_'.$event.'_debit', $operationDate, $note, $userId);
        $this->createAccountingLine($mapping->id, $creditAccountId, $amount, 'credit', 'construction_labor_'.$event.'_credit', $operationDate, $note, $userId);

        return ConstructionAccountingPosting::create([
            'business_id' => $sheet->business_id,
            'source_type' => 'labor_sheet',
            'source_id' => $sheet->id,
            'event' => $event,
            'accounting_mapping_id' => $mapping->id,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);
    }
}
