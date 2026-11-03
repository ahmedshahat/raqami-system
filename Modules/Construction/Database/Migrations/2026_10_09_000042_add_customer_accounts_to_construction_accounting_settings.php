<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_receivable_account_id')->nullable()->after('construction_revenue_account_id');
            $table->unsignedBigInteger('customer_advance_account_id')->nullable()->after('customer_retention_account_id');
            $table->unsignedBigInteger('customer_deduction_account_id')->nullable()->after('customer_advance_account_id');
            $table->unsignedBigInteger('sales_tax_payable_account_id')->nullable()->after('customer_deduction_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->dropColumn([
                'customer_receivable_account_id',
                'customer_advance_account_id',
                'customer_deduction_account_id',
                'sales_tax_payable_account_id',
            ]);
        });
    }
};
