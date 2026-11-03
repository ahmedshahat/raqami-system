<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_accounting_postings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('source_type', 80);
            $table->unsignedInteger('source_id');
            $table->string('event', 60);
            $table->unsignedBigInteger('accounting_mapping_id');
            $table->unsignedInteger('posted_by')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(
                ['business_id', 'source_type', 'source_id', 'event'],
                'construction_accounting_postings_source_unique'
            );
            $table->index('accounting_mapping_id', 'construction_accounting_postings_mapping_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_accounting_postings');
    }
};
