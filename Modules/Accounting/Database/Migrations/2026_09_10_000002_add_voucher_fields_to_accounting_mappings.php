<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->unsignedInteger('business_location_id')->nullable()->after('business_id');
            $table->string('party_name')->nullable()->after('operation_date');
            $table->string('payment_method', 50)->nullable()->after('party_name');
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->string('status', 20)->default('active')->after('payment_reference');
            $table->dateTime('cancelled_at')->nullable()->after('status');
            $table->unsignedInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->index(['business_id', 'type', 'status'], 'acc_mapping_voucher_status_idx');
        });
    }

    public function down()
    {
        Schema::table('accounting_acc_trans_mappings', function (Blueprint $table) {
            $table->dropIndex('acc_mapping_voucher_status_idx');
            $table->dropColumn([
                'business_location_id',
                'party_name',
                'payment_method',
                'payment_reference',
                'status',
                'cancelled_at',
                'cancelled_by',
            ]);
        });
    }
};
