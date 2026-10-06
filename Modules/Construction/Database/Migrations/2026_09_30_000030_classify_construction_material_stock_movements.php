<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('construction_material_document_id')->nullable()->after('construction_boq_item_id');
            $table->index('construction_material_document_id', 'transactions_construction_material_document_index');
        });

        DB::table('construction_material_documents')
            ->whereNotNull('stock_adjustment_transaction_id')
            ->orderBy('id')
            ->get(['id', 'stock_adjustment_transaction_id'])
            ->each(function ($document) {
                DB::table('transactions')->where('id', $document->stock_adjustment_transaction_id)
                    ->update(['construction_material_document_id' => $document->id]);
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_construction_material_document_index');
            $table->dropColumn('construction_material_document_id');
        });
    }
};
