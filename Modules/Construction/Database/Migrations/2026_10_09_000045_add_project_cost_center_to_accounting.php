<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->unsignedInteger('construction_project_id')->nullable()->after('business_id');
            $table->index(['business_id', 'construction_project_id'], 'accounting_mappings_project_index');
        });
        Schema::table('accounting_accounts_transactions', function (Blueprint $table) {
            $table->unsignedInteger('construction_project_id')->nullable()->after('accounting_account_id');
            $table->index(['construction_project_id', 'operation_date'], 'accounting_transactions_project_index');
        });

        DB::table('accounting_accounts_transactions as aat')
            ->join('transactions as transactions', 'transactions.id', '=', 'aat.transaction_id')
            ->where('transactions.type', 'expense')
            ->whereNotNull('transactions.construction_project_id')
            ->update(['aat.construction_project_id' => DB::raw('transactions.construction_project_id')]);
    }

    public function down(): void
    {
        Schema::table('accounting_accounts_transactions', function (Blueprint $table) {
            $table->dropIndex('accounting_transactions_project_index');
            $table->dropColumn('construction_project_id');
        });
        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->dropIndex('accounting_mappings_project_index');
            $table->dropColumn('construction_project_id');
        });
    }
};
