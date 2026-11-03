<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->date('retention_due_date')->nullable()->after('retention_value');
            $table->index(['business_id', 'retention_due_date'], 'construction_customer_retention_due_index');
        });
        Schema::table('construction_subcontract_certificates', function (Blueprint $table) {
            $table->date('retention_due_date')->nullable()->after('retention_value');
            $table->index(['business_id', 'retention_due_date'], 'construction_subcontract_retention_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->dropIndex('construction_customer_retention_due_index');
            $table->dropColumn('retention_due_date');
        });
        Schema::table('construction_subcontract_certificates', function (Blueprint $table) {
            $table->dropIndex('construction_subcontract_retention_due_index');
            $table->dropColumn('retention_due_date');
        });
    }
};
