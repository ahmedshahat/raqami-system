<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE construction_labor_sheet_lines MODIFY calculation_type ENUM('hour','day','linear_meter','square_meter','cubic_meter','unit','lump_sum') NOT NULL");
    }

    public function down(): void
    {
        DB::table('construction_labor_sheet_lines')
            ->whereIn('calculation_type', ['linear_meter', 'square_meter', 'cubic_meter', 'unit'])
            ->update(['calculation_type' => 'lump_sum', 'quantity' => 1]);
        DB::statement("ALTER TABLE construction_labor_sheet_lines MODIFY calculation_type ENUM('hour','day','lump_sum') NOT NULL");
    }
};
