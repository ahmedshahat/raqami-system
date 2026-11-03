<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('construction_contracts')
            ->whereNull('boq_version_id')
            ->orderBy('id')
            ->chunkById(100, function ($contracts) {
                foreach ($contracts as $contract) {
                    $boqVersionId = DB::table('construction_boq_versions')
                        ->where('business_id', $contract->business_id)
                        ->where('project_id', $contract->project_id)
                        ->whereIn('status', ['approved', 'superseded'])
                        ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
                        ->orderByDesc('version_number')
                        ->value('id');
                    $projectName = DB::table('construction_projects')->where('id', $contract->project_id)->value('name');
                    $updates = [];

                    if ($boqVersionId) {
                        $updates['boq_version_id'] = $boqVersionId;
                    }
                    if (! $contract->title && $projectName) {
                        $updates['title'] = 'عقد مشروع '.$projectName;
                    }
                    if ($updates) {
                        DB::table('construction_contracts')->where('id', $contract->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Existing contract links are intentionally preserved on rollback.
    }
};
