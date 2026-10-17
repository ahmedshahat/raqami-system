<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_quote_import_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('token')->unique();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('quote_id');
            $table->unsignedInteger('user_id');
            $table->string('file_name', 255);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->longText('rows_json');
            $table->enum('status', ['preview', 'imported', 'expired'])->default('preview');
            $table->timestamp('expires_at');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'quote_id', 'status'], 'construction_quote_import_batch_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_quote_import_batches');
    }
};
