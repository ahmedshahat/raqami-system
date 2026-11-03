<?php

use App\Events\TransactionPaymentAdded;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Listeners\MapOpeningBalanceTransaction;
use Modules\Accounting\Listeners\MapPaymentTransaction;

class MapContactOpeningBalances extends Migration
{
    public function up()
    {
        $openingBalanceListener = app(MapOpeningBalanceTransaction::class);

        Transaction::where('type', 'opening_balance')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($openingBalanceListener) {
                foreach ($transactions as $transaction) {
                    $openingBalanceListener->sync($transaction);
                }
            });

        $directPaymentIds = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('t.type', 'opening_balance')
            ->whereNull('tp.parent_id')
            ->pluck('tp.id');

        $parentPaymentIds = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('t.type', 'opening_balance')
            ->whereNotNull('tp.parent_id')
            ->pluck('tp.parent_id');

        $paymentIds = $directPaymentIds->merge($parentPaymentIds)->unique()->values();
        $paymentListener = app(MapPaymentTransaction::class);

        TransactionPayment::whereIn('id', $paymentIds)
            ->orderBy('id')
            ->chunkById(200, function ($payments) use ($paymentListener) {
                foreach ($payments as $payment) {
                    $paymentListener->handle(new TransactionPaymentAdded($payment));
                }
            });
    }

    public function down()
    {
        DB::table('accounting_accounts_transactions')
            ->where('sub_type', 'contact_opening_balance')
            ->delete();
    }
}
