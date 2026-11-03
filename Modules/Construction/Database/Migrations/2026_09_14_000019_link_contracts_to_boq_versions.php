<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            $table->unsignedInteger('boq_version_id')->nullable()->after('project_id');
            $table->string('title', 190)->nullable()->after('contract_number');
            $table->timestamp('activated_at')->nullable()->after('status');
            $table->unsignedInteger('activated_by')->nullable()->after('activated_at');
            $table->index(['business_id', 'boq_version_id'], 'construction_contracts_boq_version_index');
        });
    }

    public function down(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            $table->dropIndex('construction_contracts_boq_version_index');
            $table->dropColumn(['boq_version_id', 'title', 'activated_at', 'activated_by']);
        });
    }
};
