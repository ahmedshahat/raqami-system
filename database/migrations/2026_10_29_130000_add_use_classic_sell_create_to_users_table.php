<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The redesigned create-sale screen is the default; a user can switch back to
     * the classic screen for himself (users.use_classic_sell_create = 1).
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'use_classic_sell_create')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('use_classic_sell_create')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'use_classic_sell_create')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('use_classic_sell_create');
            });
        }
    }
};
