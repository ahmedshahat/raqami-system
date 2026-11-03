<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TranslateDefaultAccountNamesToArabic extends Migration
{
    public function up()
    {
        $accounts = require base_path('Modules/Accounting/Config/default_accounts_ar.php');

        foreach ($accounts as $english_name => $arabic_account) {
            DB::table('accounting_accounts')
                ->where('name', $english_name)
                ->update([
                    'name' => $arabic_account['name'],
                    'description' => $arabic_account['description'],
                ]);
        }
    }

    public function down()
    {
        $accounts = require base_path('Modules/Accounting/Config/default_accounts_ar.php');

        foreach ($accounts as $english_name => $arabic_account) {
            DB::table('accounting_accounts')
                ->where('name', $arabic_account['name'])
                ->where('description', $arabic_account['description'])
                ->update([
                    'name' => $english_name,
                    'description' => null,
                ]);
        }
    }
}
