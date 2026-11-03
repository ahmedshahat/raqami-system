<?php

namespace Tests\Unit;

use App\AccountTransaction;
use App\Events\TransactionPaymentDeleted;
use App\Listeners\DeleteAccountTransaction;
use App\TransactionPayment;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeleteAccountTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deleted_payment_removes_its_account_transaction_even_if_account_changed(): void
    {
        $paymentId = 987654321;

        $accountTransaction = AccountTransaction::create([
            'account_id' => 10,
            'type' => 'credit',
            'amount' => 1600,
            'operation_date' => now(),
            'created_by' => 1,
            'transaction_payment_id' => $paymentId,
        ]);

        $payment = new TransactionPayment();
        $payment->id = $paymentId;
        $payment->account_id = null;
        $payment->method = 'cash';

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('isModuleEnabled')
            ->once()
            ->with('account')
            ->andReturnTrue();

        $listener = new DeleteAccountTransaction(
            $this->mock(TransactionUtil::class),
            $moduleUtil
        );

        $listener->handle(new TransactionPaymentDeleted($payment));

        $this->assertSoftDeleted('account_transactions', [
            'id' => $accountTransaction->id,
            'transaction_payment_id' => $paymentId,
        ]);
    }
}
