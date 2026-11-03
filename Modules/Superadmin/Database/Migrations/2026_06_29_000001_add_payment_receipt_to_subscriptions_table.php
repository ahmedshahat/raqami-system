<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentReceiptToSubscriptionsTable extends Migration
{
    public function up()
    {
        if (
            Schema::hasTable('subscriptions') &&
            ! Schema::hasColumn('subscriptions', 'payment_receipt')
        ) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table
                    ->string('payment_receipt')
                    ->nullable()
                    ->after('payment_transaction_id');
            });
        }
    }

    public function down()
    {
        if (
            Schema::hasTable('subscriptions') &&
            Schema::hasColumn('subscriptions', 'payment_receipt')
        ) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('payment_receipt');
            });
        }
    }
}