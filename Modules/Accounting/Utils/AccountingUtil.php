<?php

namespace Modules\Accounting\Utils;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\Util;
use DB;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingAccountType;

class AccountingUtil extends Util
{
    public function balanceFormula($accounting_accounts_alias = 'accounting_accounts',
                                 $accounting_account_transaction_alias = 'AAT')
    {
        return "SUM( IF(
            ($accounting_accounts_alias.account_primary_type='asset' AND $accounting_account_transaction_alias.type='debit')
            OR ($accounting_accounts_alias.account_primary_type='expenses' AND $accounting_account_transaction_alias.type='debit')
            OR ($accounting_accounts_alias.account_primary_type='income' AND $accounting_account_transaction_alias.type='credit')
            OR ($accounting_accounts_alias.account_primary_type='equity' AND $accounting_account_transaction_alias.type='credit')
            OR ($accounting_accounts_alias.account_primary_type='liability' AND $accounting_account_transaction_alias.type='credit'), 
            amount, -1*amount)) as balance";
    }

    public function getAccountingSettings($business_id)
    {
        $accounting_settings = Business::where('id', $business_id)
                                ->value('accounting_settings');

        $accounting_settings = ! empty($accounting_settings) ? json_decode($accounting_settings, true) : [];

        return $accounting_settings;
    }

    public function getPaymentEntryTypes($is_return)
    {
        return $is_return
            ? ['payment_account' => 'debit', 'deposit_to' => 'credit']
            : ['payment_account' => 'credit', 'deposit_to' => 'debit'];
    }

    /**
     * Returns the debit/credit direction for a general cash receipt or payment voucher.
     */
    public function getVoucherEntryTypes($voucher_type)
    {
        if ($voucher_type === 'receipt_voucher') {
            return ['cash_account' => 'debit', 'offset_account' => 'credit'];
        }

        if ($voucher_type === 'payment_voucher') {
            return ['cash_account' => 'credit', 'offset_account' => 'debit'];
        }

        throw new \InvalidArgumentException('Unsupported voucher type.');
    }

    public function calculateNormalBalance($primary_type, $debit, $credit)
    {
        return in_array($primary_type, ['asset', 'expenses'], true)
            ? (float) $debit - (float) $credit
            : (float) $credit - (float) $debit;
    }

    public function getAgeingReport($business_id, $type, $group_by, $location_id = null)
    {
        $today = \Carbon::now()->format('Y-m-d');
        $query = Transaction::where('transactions.business_id', $business_id);

        if ($type == 'sell') {
            $query->where('transactions.type', 'sell')
            ->where('transactions.status', 'final');
        } elseif ($type == 'purchase') {
            $query->where('transactions.type', 'purchase')
                ->where('transactions.status', 'received');
        }

        if (! empty($location_id)) {
            $query->where('transactions.location_id', $location_id);
        }

        $dues = $query->whereNotNull('transactions.pay_term_number')
                ->whereIn('transactions.payment_status', ['partial', 'due'])
                ->join('contacts as c', 'c.id', '=', 'transactions.contact_id')
                ->select(
                    DB::raw(
                        'DATEDIFF(
                            "'.$today.'", 
                            IF(
                                transactions.pay_term_type="days",
                                DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY),
                                DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH)
                            )
                        ) as diff'
                    ),
                    DB::raw('SUM(transactions.final_total - 
                        (SELECT COALESCE(SUM(IF(tp.is_return = 1, -1*tp.amount, tp.amount)), 0) 
                        FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id) )  
                        as total_due'),

                    'c.name as contact_name',
                    'transactions.contact_id',
                    'transactions.invoice_no',
                    'transactions.ref_no',
                    'transactions.transaction_date',
                    DB::raw('IF(
                        transactions.pay_term_type="days",
                        DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY),
                        DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH)
                    ) as due_date')
                )
                ->groupBy('transactions.id')
                ->get();

        $report_details = [];
        if ($group_by == 'contact') {
            foreach ($dues as $due) {
                if (! isset($report_details[$due->contact_id])) {
                    $report_details[$due->contact_id] = [
                        'name' => $due->contact_name,
                        '<1' => 0,
                        '1_30' => 0,
                        '31_60' => 0,
                        '61_90' => 0,
                        '>90' => 0,
                        'total_due' => 0,
                    ];
                }

                if ($due->diff < 1) {
                    $report_details[$due->contact_id]['<1'] += $due->total_due;
                } elseif ($due->diff >= 1 && $due->diff <= 30) {
                    $report_details[$due->contact_id]['1_30'] += $due->total_due;
                } elseif ($due->diff >= 31 && $due->diff <= 60) {
                    $report_details[$due->contact_id]['31_60'] += $due->total_due;
                } elseif ($due->diff >= 61 && $due->diff <= 90) {
                    $report_details[$due->contact_id]['61_90'] += $due->total_due;
                } elseif ($due->diff > 90) {
                    $report_details[$due->contact_id]['>90'] += $due->total_due;
                }

                $report_details[$due->contact_id]['total_due'] += $due->total_due;
            }
        } elseif ($group_by == 'due_date') {
            $report_details = [
                'current' => [],
                '1_30' => [],
                '31_60' => [],
                '61_90' => [],
                '>90' => [],
            ];
            foreach ($dues as $due) {
                $temp_array = [
                    'transaction_date' => $this->format_date($due->transaction_date),
                    'due_date' => $this->format_date($due->due_date),
                    'ref_no' => $due->ref_no,
                    'invoice_no' => $due->invoice_no,
                    'contact_name' => $due->contact_name,
                    'due' => $due->total_due,
                ];
                if ($due->diff < 1) {
                    $report_details['current'][] = $temp_array;
                } elseif ($due->diff >= 1 && $due->diff <= 30) {
                    $report_details['1_30'][] = $temp_array;
                } elseif ($due->diff >= 31 && $due->diff <= 60) {
                    $report_details['31_60'][] = $temp_array;
                } elseif ($due->diff >= 61 && $due->diff <= 90) {
                    $report_details['61_90'][] = $temp_array;
                } elseif ($due->diff > 90) {
                    $report_details['>90'][] = $temp_array;
                }
            }
        }

        return $report_details;
    }

    /**
     * Function to delete a mapping
     */
    public function deleteMap($transaction_id, $transaction_payment_id){
        AccountingAccountsTransaction::where('transaction_id', $transaction_id)
            ->whereIn('map_type', ['payment_account', 'deposit_to'])
            ->where('transaction_payment_id', $transaction_payment_id)
            ->delete();
    }

    public function deleteContactOpeningBalanceMap($transaction_id)
    {
        AccountingAccountsTransaction::where('transaction_id', $transaction_id)
            ->whereIn('map_type', ['contact_opening_balance', 'opening_balance_equity'])
            ->delete();
    }

    /**
     * Post a contact opening balance to receivables/payables and opening equity.
     */
    public function syncContactOpeningBalance($transaction)
    {
        $this->deleteContactOpeningBalanceMap($transaction->id);

        if ($transaction->type !== 'opening_balance' || (float) $transaction->final_total <= 0) {
            return;
        }

        $contact = Contact::where('business_id', $transaction->business_id)
            ->find($transaction->contact_id);
        if (empty($contact)) {
            return;
        }

        $is_supplier = $contact->type === 'supplier';
        $map_candidates = $is_supplier
            ? [['purchases', 'payment_account'], ['purchase_payment', 'deposit_to']]
            : [['sale', 'deposit_to'], ['sell_payment', 'payment_account']];
        $expected_primary_type = $is_supplier ? 'liability' : 'asset';

        // Opening balances are contact-level entries and are not tied to the
        // location currently selected by the user. Prefer the transaction's
        // location, then fall back to any business location which has the
        // appropriate receivables/payables control account configured.
        $locations = BusinessLocation::where('business_id', $transaction->business_id)
            ->orderByRaw('id = ? DESC', [$transaction->location_id])
            ->get();
        $control_account = null;
        foreach ($locations as $business_location) {
            $accounting_default_map = json_decode($business_location->accounting_default_map ?: '[]', true);
            foreach ($map_candidates as [$map_section, $map_key]) {
                $control_account_id = $accounting_default_map[$map_section][$map_key] ?? null;
                if (empty($control_account_id)) {
                    continue;
                }

                $control_account = AccountingAccount::where('business_id', $transaction->business_id)
                    ->where('status', 'active')
                    ->where('account_primary_type', $expected_primary_type)
                    ->find($control_account_id);
                if (! empty($control_account)) {
                    break 2;
                }
            }
        }

        if (empty($control_account)) {
            return;
        }

        $opening_equity_detail = AccountingAccountType::where('name', 'opening_balance_equity')
            ->where('account_type', 'detail_type')
            ->whereNull('business_id')
            ->first();
        if (empty($opening_equity_detail)) {
            return;
        }

        $opening_equity_account = AccountingAccount::firstOrCreate(
            [
                'business_id' => $transaction->business_id,
                'detail_type_id' => $opening_equity_detail->id,
            ],
            [
                'name' => __('accounting::lang.opening_balance_equity'),
                'account_primary_type' => 'equity',
                'account_sub_type_id' => $opening_equity_detail->parent_id,
                'status' => 'active',
                'created_by' => $transaction->created_by,
            ]
        );

        $common = [
            'transaction_id' => $transaction->id,
            'transaction_payment_id' => null,
            'amount' => $transaction->final_total,
            'sub_type' => 'contact_opening_balance',
            'created_by' => $transaction->created_by,
            'operation_date' => $transaction->transaction_date,
            'note' => $contact->name,
        ];

        AccountingAccountsTransaction::updateOrCreateMapTransaction(array_merge($common, [
            'accounting_account_id' => $control_account->id,
            'type' => $is_supplier ? 'credit' : 'debit',
            'map_type' => 'contact_opening_balance',
        ]));

        AccountingAccountsTransaction::updateOrCreateMapTransaction(array_merge($common, [
            'accounting_account_id' => $opening_equity_account->id,
            'type' => $is_supplier ? 'debit' : 'credit',
            'map_type' => 'opening_balance_equity',
        ]));
    }

    /**
     * Function to save a mapping
     */
    public function saveMap($type, $id, $user_id, $business_id, $deposit_to, $payment_account, $note = null){
        $account_ids = array_values(array_unique(array_filter([$deposit_to, $payment_account])));
        $owned_accounts = AccountingAccount::where('business_id', $business_id)
            ->whereIn('id', $account_ids)
            ->count();

        if (count($account_ids) !== 2 || $owned_accounts !== 2) {
            abort(422, __('accounting::lang.invalid_accounting_input'));
        }

        if ($type === 'sell') {
            $transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->where('id', $id)
                ->firstOrFail();

            if ((float) $transaction->final_total <= 0) {
                abort(422, __('accounting::lang.invalid_accounting_input'));
            }

            //$payment_account will increase = sales = credit
            $payment_data = [
                'accounting_account_id' => $payment_account,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'credit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'payment_account',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
                'construction_project_id' => $transaction->construction_project_id,
            ];

            //Deposit to will increase = debit
            $deposit_data = [
                'accounting_account_id' => $deposit_to,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'debit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'deposit_to',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
                'construction_project_id' => $transaction->construction_project_id,
            ];
        } elseif (in_array($type, ['purchase_payment', 'sell_payment'], true)) {
            $transaction_payment = TransactionPayment::where('id', $id)->where('business_id', $business_id)
                            ->firstOrFail();
            $expected_transaction_type = $type === 'sell_payment' ? 'sell' : 'purchase';
            $expected_status = $type === 'sell_payment' ? 'final' : 'received';
            if (! empty($transaction_payment->transaction_id)) {
                $source_transaction = Transaction::where('business_id', $business_id)
                    ->where('id', $transaction_payment->transaction_id)
                    ->firstOrFail();
                $valid_source = ($source_transaction->type === $expected_transaction_type
                        && $source_transaction->status === $expected_status)
                    || $source_transaction->type === 'opening_balance';
                abort_unless($valid_source, 422, __('accounting::lang.invalid_accounting_input'));
            } else {
                $contact = Contact::where('business_id', $business_id)
                    ->findOrFail($transaction_payment->payment_for);
                $expected_contact_type = $type === 'sell_payment' ? ['customer', 'both'] : ['supplier'];
                abort_unless(in_array($contact->type, $expected_contact_type, true), 422, __('accounting::lang.invalid_accounting_input'));
            }

            if ((float) $transaction_payment->amount <= 0) {
                abort(422, __('accounting::lang.invalid_accounting_input'));
            }

            $entry_types = $this->getPaymentEntryTypes((bool) $transaction_payment->is_return);
            $payment_type = $entry_types['payment_account'];
            $deposit_type = $entry_types['deposit_to'];

            //$payment_account will increase = sales = credit
            $payment_data = [
                'accounting_account_id' => $payment_account,
                'transaction_id' => null,
                'transaction_payment_id' => $id,
                'amount' => $transaction_payment->amount,
                'type' => $payment_type,
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'payment_account',
                'created_by' => $user_id,
                'operation_date' => $transaction_payment->paid_on ?? $transaction_payment->created_at ?? \Carbon::now(),
            ];

            //Deposit to will increase = debit
            $deposit_data = [
                'accounting_account_id' => $deposit_to,
                'transaction_id' => null,
                'transaction_payment_id' => $id,
                'amount' => $transaction_payment->amount,
                'type' => $deposit_type,
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'deposit_to',
                'created_by' => $user_id,
                'operation_date' => $transaction_payment->paid_on ?? $transaction_payment->created_at ?? \Carbon::now(),
            ];
        } elseif ($type === 'purchase') {
            $transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->where('status', 'received')
                ->where('id', $id)
                ->firstOrFail();

            if ((float) $transaction->final_total <= 0) {
                abort(422, __('accounting::lang.invalid_accounting_input'));
            }

            //$payment_account will increase = sales = credit
            $payment_data = [
                'accounting_account_id' => $payment_account,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'credit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'payment_account',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
            ];

            //Deposit to will increase = debit
            $deposit_data = [
                'accounting_account_id' => $deposit_to,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'debit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'deposit_to',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
            ];
        } elseif ($type === 'expense') {
            $transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'expense')
                ->where('id', $id)
                ->firstOrFail();

            if ((float) $transaction->final_total <= 0) {
                abort(422, __('accounting::lang.invalid_accounting_input'));
            }
            $payment_data = [
                'accounting_account_id' => $payment_account,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'credit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'payment_account',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
                'construction_project_id' => $transaction->construction_project_id,
            ];

            $deposit_data = [
                'accounting_account_id' => $deposit_to,
                'transaction_id' => $id,
                'transaction_payment_id' => null,
                'amount' => $transaction->final_total,
                'type' => 'debit',
                'sub_type' => $type,
                'note' => $note,
                'map_type' => 'deposit_to',
                'created_by' => $user_id,
                'operation_date' => $transaction->transaction_date,
                'construction_project_id' => $transaction->construction_project_id,
            ];
        } else {
            abort(422, __('accounting::lang.invalid_accounting_input'));
        }

        AccountingAccountsTransaction::updateOrCreateMapTransaction($payment_data);
        AccountingAccountsTransaction::updateOrCreateMapTransaction($deposit_data);
    }
}
