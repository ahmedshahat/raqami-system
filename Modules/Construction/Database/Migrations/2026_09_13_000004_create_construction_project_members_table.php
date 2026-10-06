<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_project_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('user_id');
            $table->string('access_level', 20)->default('view');
            $table->unsignedInteger('assigned_by');
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'construction_project_member_unique');
            $table->index(['business_id', 'user_id'], 'construction_members_business_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_project_members');
    }
};
