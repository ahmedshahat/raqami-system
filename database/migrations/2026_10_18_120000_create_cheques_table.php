<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cheques register: customer cheques (receivable) and supplier cheques (payable).
     * A cheque is not a payment until it is collected / cleared.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('cheques')) {
            return;
        }

        Schema::create('cheques', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('type', 20)->comment('receivable, payable');
            $table->unsignedInteger('contact_id')->index();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 22, 4)->default(0);
            $table->dateTime('received_on')->nullable()->comment('Date the cheque was received / issued');
            $table->date('due_date')->index();
            $table->string('status', 20)->default('pending')->index()->comment('pending, collected, bounced, cancelled');
            $table->unsignedInteger('account_id')->nullable()->comment('Suggested / used payment account');
            $table->unsignedInteger('payment_id')->nullable()->comment('Payment created on collection');
            $table->dateTime('status_changed_on')->nullable();
            $table->text('note')->nullable();
            $table->text('status_note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type', 'status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cheques');
    }
};
