<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_boq_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_id')->nullable()->after('unit');
            $table->index(['business_id', 'unit_id'], 'construction_boq_items_business_unit_index');
        });

        DB::table('construction_boq_items')->whereNotNull('unit')->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $unit = DB::table('units')->where('business_id', $item->business_id)
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($item) {
                        $query->where('short_name', $item->unit)->orWhere('actual_name', $item->unit);
                    })->orderBy('id')->first();
                if ($unit) {
                    DB::table('construction_boq_items')->where('id', $item->id)->update(['unit_id' => $unit->id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('construction_boq_items', function (Blueprint $table) {
            $table->dropIndex('construction_boq_items_business_unit_index');
            $table->dropColumn('unit_id');
        });
    }
};
