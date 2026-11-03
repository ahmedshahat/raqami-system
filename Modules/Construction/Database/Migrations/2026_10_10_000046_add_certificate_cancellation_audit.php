<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['construction_subcontract_certificates', 'construction_customer_certificates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('cancellation_reason')->nullable();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['construction_subcontract_certificates', 'construction_customer_certificates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['cancellation_reason', 'cancelled_by', 'cancelled_at']);
            });
        }
    }
};
