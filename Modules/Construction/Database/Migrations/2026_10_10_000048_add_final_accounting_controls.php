<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->unsignedInteger('credit_note_transaction_id')->nullable()->after('invoice_transaction_id');
            $table->index('credit_note_transaction_id', 'construction_customer_certificates_credit_note_index');
        });

        Schema::create('construction_expense_cancellations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('expense_transaction_id')->unique();
            $table->unsignedBigInteger('accounting_mapping_id');
            $table->text('reason');
            $table->unsignedInteger('cancelled_by');
            $table->timestamp('cancelled_at');
            $table->timestamps();
            $table->index(['business_id', 'project_id'], 'construction_expense_cancellations_project_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_expense_cancellations');
        Schema::table('construction_customer_certificates', function (Blueprint $table) {
            $table->dropIndex('construction_customer_certificates_credit_note_index');
            $table->dropColumn('credit_note_transaction_id');
        });
    }
};
