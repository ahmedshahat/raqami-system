<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveOrphanedAccountingTransactions extends Migration
{
    /**
     * Remove generic accounting mappings whose source invoice/payment no
     * longer exists. These rows otherwise remain visible in the ledger with
     * an amount but no invoice number.
     */
    public function up()
    {
        DB::table('accounting_accounts_transactions')
            ->whereIn('sub_type', ['sell', 'purchase', 'expense'])
            ->where(function ($query) {
                $query->whereNull('transaction_id')
                    ->orWhereNotExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn(
                                'transactions.id',
                                'accounting_accounts_transactions.transaction_id'
                            );
                    });
            })
            ->delete();

        DB::table('accounting_accounts_transactions')
            ->whereIn('sub_type', ['sell_payment', 'purchase_payment'])
            ->where(function ($query) {
                $query->whereNull('transaction_payment_id')
                    ->orWhereNotExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('transaction_payments')
                            ->whereColumn(
                                'transaction_payments.id',
                                'accounting_accounts_transactions.transaction_payment_id'
                            );
                    });
            })
            ->delete();
    }

    public function down()
    {
        // Deleted orphan mappings cannot be reconstructed without their
        // original invoices or payments.
    }
}
