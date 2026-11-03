<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_expense_cancellations', function (Blueprint $table) {
            $table->unsignedInteger('refund_transaction_id')->nullable()->after('expense_transaction_id');
            $table->unique('refund_transaction_id', 'construction_expense_cancellations_refund_unique');
        });
    }

    public function down(): void
    {
        Schema::table('construction_expense_cancellations', function (Blueprint $table) {
            $table->dropUnique('construction_expense_cancellations_refund_unique');
            $table->dropColumn('refund_transaction_id');
        });
    }
};
