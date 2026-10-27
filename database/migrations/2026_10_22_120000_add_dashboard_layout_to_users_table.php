<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user layout of the home dashboard (home/v2): section order per zone
     * and hidden sections, as JSON.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'dashboard_layout')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('dashboard_layout')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'dashboard_layout')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dashboard_layout');
            });
        }
    }
};
