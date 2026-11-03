<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The redesigned dashboard is the default home; a user can switch back to
     * the classic home for himself (users.use_classic_home = 1).
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'use_classic_home')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('use_classic_home')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'use_classic_home')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('use_classic_home');
            });
        }
    }
};
