<?php

use App\Transaction;
use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Listeners\MapOpeningBalanceTransaction;

class ResyncContactOpeningBalances extends Migration
{
    public function up()
    {
        $listener = app(MapOpeningBalanceTransaction::class);

        Transaction::where('type', 'opening_balance')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($listener) {
                foreach ($transactions as $transaction) {
                    $listener->sync($transaction);
                }
            });
    }

    public function down()
    {
        // The previous version already manages these entries. Rolling back
        // this repair must not delete valid opening-balance accounting data.
    }
}
