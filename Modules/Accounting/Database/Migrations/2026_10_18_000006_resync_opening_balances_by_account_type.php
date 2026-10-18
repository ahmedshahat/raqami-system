<?php

use App\Transaction;
use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Listeners\MapOpeningBalanceTransaction;

class ResyncOpeningBalancesByAccountType extends Migration
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
        // Keep valid accounting entries intact when rolling back this repair.
    }
}
