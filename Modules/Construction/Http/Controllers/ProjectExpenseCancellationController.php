<?php

namespace Modules\Construction\Http\Controllers;

use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Construction\Entities\ConstructionExpenseCancellation;

class ProjectExpenseCancellationController extends BaseController
{
    public function store(Request $request, int $expense)
    {
        $this->authorizePermission('construction.cancel');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => __('construction::lang.invalid_manager_password')]);
        }

        DB::transaction(function () use ($expense, $validated) {
            $expense = Transaction::where('business_id', $this->businessId())
                ->where('type', 'expense')->whereNotNull('construction_project_id')
                ->lockForUpdate()->findOrFail($expense);
            abort_if(ConstructionExpenseCancellation::where('expense_transaction_id', $expense->id)->exists(), 422, __('construction::lang.project_expense_already_cancelled'));

            $lines = AccountingAccountsTransaction::where('transaction_id', $expense->id)->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['cancellation_reason' => __('construction::lang.project_expense_entry_missing')]);
            }
            $debit = round((float) $lines->where('type', 'debit')->sum('amount'), 4);
            $credit = round((float) $lines->where('type', 'credit')->sum('amount'), 4);
            if (abs($debit - $credit) > 0.0001) {
                throw ValidationException::withMessages(['cancellation_reason' => __('construction::lang.project_expense_entry_unbalanced')]);
            }

            $operationDate = now()->startOfDay();
            $note = __('construction::lang.project_expense_reversal_note', ['reference' => $expense->ref_no ?: $expense->id]);
            $mapping = new AccountingAccTransMapping();
            $mapping->business_id = $expense->business_id;
            $mapping->construction_project_id = $expense->construction_project_id;
            $mapping->ref_no = 'CT-EXP-CAN-'.($expense->ref_no ?: $expense->id);
            $mapping->type = 'journal_entry';
            $mapping->created_by = auth()->id();
            $mapping->operation_date = $operationDate;
            $mapping->status = 'active';
            $mapping->note = $note;
            $mapping->save();

            foreach ($lines as $line) {
                AccountingAccountsTransaction::create([
                    'accounting_account_id' => $line->accounting_account_id,
                    'construction_project_id' => $expense->construction_project_id,
                    'acc_trans_mapping_id' => $mapping->id,
                    'amount' => $line->amount,
                    'type' => $line->type === 'debit' ? 'credit' : 'debit',
                    'sub_type' => 'journal_entry',
                    'map_type' => 'construction_expense_reversal_'.$line->map_type,
                    'created_by' => auth()->id(),
                    'operation_date' => $operationDate,
                    'note' => $note,
                ]);
            }

            // Use the ERP's native expense-refund document to make every standard
            // expense report net this cancellation to zero. The GL reversal above
            // remains authoritative, so this audit document is deliberately not
            // dispatched through the generic Accounting listener.
            $refund = $expense->replicate([
                'id', 'ref_no', 'type', 'source', 'construction_project_id',
                'construction_boq_item_id', 'document', 'is_recurring',
                'recur_interval', 'recur_interval_type', 'recur_repetitions',
                'subscription_repeat_on', 'recur_parent_id', 'created_at', 'updated_at',
            ]);
            $refund->forceFill([
                'type' => 'expense_refund',
                'source' => 'construction_expense_cancellation',
                'ref_no' => 'CT-EXP-REV-'.($expense->ref_no ?: $expense->id),
                'transaction_date' => $operationDate,
                'payment_status' => 'due',
                'construction_project_id' => null,
                'construction_boq_item_id' => null,
                'additional_notes' => $note.' — '.$validated['cancellation_reason'],
                'created_by' => auth()->id(),
            ])->save();

            ConstructionExpenseCancellation::create([
                'business_id' => $expense->business_id,
                'project_id' => $expense->construction_project_id,
                'expense_transaction_id' => $expense->id,
                'refund_transaction_id' => $refund->id,
                'accounting_mapping_id' => $mapping->id,
                'reason' => $validated['cancellation_reason'],
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.project_expense_cancelled_and_reversed')]);
    }
}
