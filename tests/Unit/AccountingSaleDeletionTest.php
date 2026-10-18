<?php

namespace Tests\Unit;

use App\Events\SellCreatedOrModified;
use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Listeners\MapSellTransaction;
use Modules\Accounting\Utils\AccountingUtil;
use Tests\TestCase;

class AccountingSaleDeletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deleted_sale_removes_its_accounting_mapping(): void
    {
        $transaction = new Transaction();
        $transaction->id = 987654321;
        $transaction->business_id = 1;

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')
            ->once()->with(1, 'accounting_module')->andReturnTrue();

        $accountingUtil = $this->mock(AccountingUtil::class);
        $accountingUtil->shouldReceive('deleteMap')
            ->once()->with(987654321, null);

        app(MapSellTransaction::class)->handle(new SellCreatedOrModified($transaction, true));
    }

    public function test_rejected_sale_deletion_keeps_its_accounting_mapping(): void
    {
        $transaction = Transaction::firstOrFail();

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')
            ->once()->with($transaction->business_id, 'accounting_module')->andReturnTrue();

        $accountingUtil = $this->mock(AccountingUtil::class);
        $accountingUtil->shouldNotReceive('deleteMap');

        app(MapSellTransaction::class)->handle(new SellCreatedOrModified($transaction, true));
    }

    public function test_update_migration_removes_an_orphaned_sale_mapping(): void
    {
        $id = DB::table('accounting_accounts_transactions')->insertGetId([
            'accounting_account_id' => 1,
            'transaction_id' => 987654321,
            'transaction_payment_id' => null,
            'amount' => 83.5,
            'type' => 'debit',
            'sub_type' => 'sell',
            'map_type' => 'deposit_to',
            'created_by' => 1,
            'operation_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        require_once base_path('Modules/Accounting/Database/Migrations/2026_10_18_000003_remove_orphaned_accounting_transactions.php');
        (new \RemoveOrphanedAccountingTransactions())->up();

        $this->assertDatabaseMissing('accounting_accounts_transactions', ['id' => $id]);
    }
}
