<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('labor_payable_account_id')->nullable()->after('labor_cost_account_id');
        });

        Schema::create('construction_labor_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('labor_sheet_id');
            $table->unsignedBigInteger('accounting_account_id');
            $table->string('number', 60);
            $table->date('payment_date');
            $table->decimal('amount', 22, 4);
            $table->string('method', 40);
            $table->string('reference_no', 120)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['recorded', 'cancelled'])->default('recorded');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_labor_payments_number_unique');
            $table->index(['labor_sheet_id', 'status'], 'construction_labor_payments_sheet_index');
            $table->index(['business_id', 'project_id'], 'construction_labor_payments_project_index');
            $table->index('accounting_account_id', 'construction_labor_payments_accounting_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_labor_payments');
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->dropColumn('labor_payable_account_id');
        });
    }
};
