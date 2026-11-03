<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_accounting_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->unique();
            foreach ([
                'construction_revenue_account_id', 'customer_retention_account_id',
                'inventory_account_id', 'material_cost_account_id', 'labor_cost_account_id',
                'subcontract_cost_account_id', 'subcontract_payable_account_id',
                'subcontract_retention_account_id', 'subcontract_deduction_account_id',
                'cash_account_id',
            ] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_accounting_settings');
    }
};
