<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user appearance (light / dark). Null follows the business setting
     * (business.theme_color); a value here overrides it for this user only.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'theme_mode')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_mode', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'theme_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('theme_mode');
            });
        }
    }
};
