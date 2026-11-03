<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_subcontract_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('accounting_account_id')->nullable()->after('account_id');
            $table->index('accounting_account_id', 'construction_subcontract_payments_accounting_index');
        });
    }

    public function down(): void
    {
        Schema::table('construction_subcontract_payments', function (Blueprint $table) {
            $table->dropIndex('construction_subcontract_payments_accounting_index');
            $table->dropColumn('accounting_account_id');
        });
    }
};
