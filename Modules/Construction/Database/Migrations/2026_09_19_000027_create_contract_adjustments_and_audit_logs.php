<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_contract_adjustments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('contract_id');
            $table->unsignedInteger('original_boq_item_id')->nullable();
            $table->unsignedInteger('boq_item_id')->nullable();
            $table->string('code', 60)->nullable();
            $table->string('description', 500);
            $table->unsignedInteger('unit_id');
            $table->string('unit', 40);
            $table->decimal('quantity', 22, 4);
            $table->decimal('unit_price', 22, 4);
            $table->decimal('total', 22, 4);
            $table->string('reason', 500);
            $table->date('adjustment_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'project_id', 'status'], 'construction_adjustments_project_status_index');
            $table->index(['contract_id', 'original_boq_item_id'], 'construction_adjustments_contract_item_index');
            $table->unique('boq_item_id', 'construction_adjustments_boq_item_unique');
        });

        Schema::create('construction_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->string('auditable_type', 190);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('event', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'project_id', 'created_at'], 'construction_audit_project_index');
            $table->index(['auditable_type', 'auditable_id'], 'construction_audit_subject_index');
        });

        Schema::table('construction_quote_items', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('construction_quote_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
        Schema::dropIfExists('construction_audit_logs');
        Schema::dropIfExists('construction_contract_adjustments');
    }
};
