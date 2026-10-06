<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE construction_labor_sheets MODIFY status ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'draft'");

        Schema::table('construction_labor_sheets', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('approved_at');
            $table->unsignedInteger('cancelled_by')->nullable()->after('cancellation_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        DB::table('construction_labor_sheets')
            ->where('status', 'cancelled')
            ->update(['status' => 'approved']);

        DB::statement("ALTER TABLE construction_labor_sheets MODIFY status ENUM('draft','approved') NOT NULL DEFAULT 'draft'");

        Schema::table('construction_labor_sheets', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_by', 'cancelled_at']);
        });
    }
};
