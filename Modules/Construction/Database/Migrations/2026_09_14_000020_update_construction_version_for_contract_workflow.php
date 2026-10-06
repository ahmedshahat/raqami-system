<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system')->updateOrInsert(
            ['key' => 'construction_version'],
            ['value' => config('construction.module_version', '0.5.0')]
        );
    }

    public function down(): void
    {
        DB::table('system')->where('key', 'construction_version')->update(['value' => '0.4.1']);
    }
};
