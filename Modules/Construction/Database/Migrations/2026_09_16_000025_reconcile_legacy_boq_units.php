<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyUnits = DB::table('construction_boq_items')
            ->where('row_type', 'item')->whereNull('unit_id')->whereNotNull('unit')
            ->select('business_id', 'unit')->distinct()->get();

        foreach ($legacyUnits as $legacy) {
            $name = trim($legacy->unit);
            if ($name === '') {
                continue;
            }
            $unit = DB::table('units')->where('business_id', $legacy->business_id)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($name) {
                    $query->where('short_name', $name)->orWhere('actual_name', $name);
                })->first();
            if (! $unit) {
                $ownerId = DB::table('business')->where('id', $legacy->business_id)->value('owner_id');
                if (! $ownerId) {
                    continue;
                }
                $unitId = DB::table('units')->insertGetId([
                    'business_id' => $legacy->business_id,
                    'actual_name' => $name,
                    'short_name' => $name,
                    'allow_decimal' => 1,
                    'created_by' => $ownerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $unitId = $unit->id;
            }
            DB::table('construction_boq_items')->where('business_id', $legacy->business_id)
                ->where('row_type', 'item')->where('unit', $legacy->unit)->whereNull('unit_id')
                ->update(['unit_id' => $unitId]);
        }
    }

    public function down(): void
    {
        // A reconciled unit can be used elsewhere; keep system units and their links.
    }
};
