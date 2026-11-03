<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_projects', function (Blueprint $table) {
            $table->unsignedInteger('next_contract_sequence')->default(1)->after('status');
        });

        Schema::table('construction_contracts', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->after('project_id');
        });

        DB::table('construction_projects')->orderBy('id')->each(function ($project) {
            $contracts = DB::table('construction_contracts')
                ->where('business_id', $project->business_id)
                ->where('project_id', $project->id)
                ->orderBy('id')
                ->get(['id']);

            foreach ($contracts as $index => $contract) {
                DB::table('construction_contracts')->where('id', $contract->id)->update([
                    'sequence_number' => $index + 1,
                ]);
            }

            DB::table('construction_projects')->where('id', $project->id)->update([
                'next_contract_sequence' => $contracts->count() + 1,
            ]);
        });

        Schema::table('construction_contracts', function (Blueprint $table) {
            $table->unique(['business_id', 'contract_number'], 'construction_contracts_business_number_unique');
            $table->unique(['business_id', 'project_id', 'sequence_number'], 'construction_contracts_project_sequence_unique');
        });

        DB::table('system')->where('key', 'construction_version')->update(['value' => '0.6.0']);
    }

    public function down(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            $table->dropUnique('construction_contracts_business_number_unique');
            $table->dropUnique('construction_contracts_project_sequence_unique');
            $table->dropColumn('sequence_number');
        });

        Schema::table('construction_projects', function (Blueprint $table) {
            $table->dropColumn('next_contract_sequence');
        });
    }
};
