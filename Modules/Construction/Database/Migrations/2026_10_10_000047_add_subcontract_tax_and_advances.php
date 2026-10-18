<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('input_tax_account_id')->nullable()->after('sales_tax_payable_account_id');
            $table->unsignedBigInteger('subcontract_advance_account_id')->nullable()->after('subcontract_deduction_account_id');
        });

        Schema::table('construction_subcontract_certificates', function (Blueprint $table) {
            $table->decimal('tax_percent', 8, 4)->default(0)->after('gross_value');
            $table->decimal('tax_value', 22, 4)->default(0)->after('tax_percent');
            $table->decimal('advance_recovery_value', 22, 4)->default(0)->after('other_deductions');
        });

        Schema::create('construction_subcontract_advances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('subcontract_id');
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedBigInteger('accounting_account_id')->nullable();
            $table->string('number', 60);
            $table->date('advance_date');
            $table->decimal('amount', 22, 4);
            $table->string('method', 40);
            $table->string('reference_no', 120)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['recorded', 'cancelled'])->default('recorded');
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_subcontract_advances_number_unique');
            $table->index(['subcontract_id', 'status'], 'construction_subcontract_advances_contract_index');
            $table->index(['business_id', 'project_id'], 'construction_subcontract_advances_project_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_subcontract_advances');
        Schema::table('construction_subcontract_certificates', function (Blueprint $table) {
            $table->dropColumn(['tax_percent', 'tax_value', 'advance_recovery_value']);
        });
        Schema::table('construction_accounting_settings', function (Blueprint $table) {
            $table->dropColumn(['input_tax_account_id', 'subcontract_advance_account_id']);
        });
    }
};
