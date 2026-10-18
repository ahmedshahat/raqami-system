<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->index(['business_id', 'status'], 'acc_accounts_business_status_idx');
            $table->index('account_sub_type_id', 'acc_accounts_sub_type_idx');
            $table->index('detail_type_id', 'acc_accounts_detail_type_idx');
            $table->index('parent_account_id', 'acc_accounts_parent_idx');
        });

        Schema::table('accounting_accounts_transactions', function (Blueprint $table) {
            $table->index(['accounting_account_id', 'operation_date'], 'acc_trans_account_date_idx');
            $table->unique(['transaction_id', 'map_type'], 'acc_trans_source_map_unique');
            $table->unique(['transaction_payment_id', 'map_type'], 'acc_trans_payment_map_unique');
            $table->index('acc_trans_mapping_id', 'acc_trans_mapping_idx');
        });

        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->index(['business_id', 'type', 'operation_date'], 'acc_mapping_business_type_date_idx');
        });

        Schema::table('accounting_budgets', function (Blueprint $table) {
            $table->unique(['accounting_account_id', 'financial_year'], 'acc_budget_account_year_unique');
        });
    }

    public function down()
    {
        Schema::table('accounting_budgets', function (Blueprint $table) {
            $table->dropUnique('acc_budget_account_year_unique');
        });

        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->dropIndex('acc_mapping_business_type_date_idx');
        });

        Schema::table('accounting_accounts_transactions', function (Blueprint $table) {
            $table->dropIndex('acc_trans_account_date_idx');
            $table->dropUnique('acc_trans_source_map_unique');
            $table->dropUnique('acc_trans_payment_map_unique');
            $table->dropIndex('acc_trans_mapping_idx');
        });

        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropIndex('acc_accounts_business_status_idx');
            $table->dropIndex('acc_accounts_sub_type_idx');
            $table->dropIndex('acc_accounts_detail_type_idx');
            $table->dropIndex('acc_accounts_parent_idx');
        });
    }
};
