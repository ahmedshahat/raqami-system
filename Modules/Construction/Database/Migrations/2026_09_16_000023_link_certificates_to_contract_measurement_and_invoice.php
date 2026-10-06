<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->unsignedInteger('contract_id')->nullable()->after('boq_version_id');
            $table->unsignedInteger('measurement_id')->nullable()->after('contract_id');
            $table->unsignedInteger('tax_rate_id')->nullable()->after('tax_percent');
            $table->unsignedInteger('invoice_transaction_id')->nullable()->after('net_due');
            $table->index(['business_id', 'measurement_id'], 'construction_certificate_measurement_index');
            $table->unique('invoice_transaction_id', 'construction_certificate_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->dropIndex('construction_certificate_measurement_index');
            $table->dropUnique('construction_certificate_invoice_unique');
            $table->dropColumn(['contract_id', 'measurement_id', 'tax_rate_id', 'invoice_transaction_id']);
        });
    }
};
