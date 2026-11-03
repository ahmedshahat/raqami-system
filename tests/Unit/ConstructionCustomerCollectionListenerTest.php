<?php

namespace Tests\Unit;

use App\Events\TransactionPaymentAdded;
use App\Events\SellCreatedOrModified;
use App\BusinessLocation;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Accounting\Listeners\MapPaymentTransaction;
use Modules\Accounting\Listeners\MapSellTransaction;
use Modules\Accounting\Utils\AccountingUtil;
use Modules\Construction\Entities\ConstructionAccountingPosting;
use Modules\Construction\Listeners\MapCustomerCollection;
use Modules\Construction\Support\ConstructionAccountingPoster;
use Tests\TestCase;

class ConstructionCustomerCollectionListenerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_construction_payment_keeps_its_generic_accounting_mapping(): void
    {
        $payment = new TransactionPayment();
        $payment->id = 901;
        $payment->business_id = 1;

        $poster = $this->mock(ConstructionAccountingPoster::class);
        $poster->shouldReceive('syncCustomerCollection')->once()->with($payment)->andReturnNull();

        $accounting = $this->mock(AccountingUtil::class);
        $accounting->shouldNotReceive('deleteMap');

        app(MapCustomerCollection::class)->handle(new TransactionPaymentAdded($payment));
    }

    public function test_construction_collection_removes_only_the_generic_payment_mapping(): void
    {
        $payment = new TransactionPayment();
        $payment->id = 902;
        $payment->business_id = 1;

        $poster = $this->mock(ConstructionAccountingPoster::class);
        $poster->shouldReceive('syncCustomerCollection')->once()->with($payment)
            ->andReturn(new ConstructionAccountingPosting());

        $accounting = $this->mock(AccountingUtil::class);
        $accounting->shouldReceive('deleteMap')->once()->with(null, 902);

        app(MapCustomerCollection::class)->handle(new TransactionPaymentAdded($payment));
    }

    public function test_standard_sale_payment_still_reaches_the_accounting_mapping(): void
    {
        $transaction = Transaction::whereNotNull('location_id')->firstOrFail();
        $transaction->forceFill([
            'type' => 'sell',
            'status' => 'final',
            'source' => null,
        ])->saveQuietly();

        BusinessLocation::whereKey($transaction->location_id)->update([
            'accounting_default_map' => json_encode([
                'sell_payment' => ['deposit_to' => 101, 'payment_account' => 102],
            ]),
        ]);

        $payment = new TransactionPayment();
        $payment->id = 903;
        $payment->business_id = $transaction->business_id;
        $payment->transaction_id = $transaction->id;
        $payment->created_by = $transaction->created_by;

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->once()
            ->with($transaction->business_id, 'accounting_module')->andReturnTrue();

        $accounting = $this->mock(AccountingUtil::class);
        $accounting->shouldReceive('saveMap')->once()->with(
            'sell_payment',
            903,
            $transaction->created_by,
            $transaction->business_id,
            101,
            102
        );
        $accounting->shouldNotReceive('deleteMap');

        app(MapPaymentTransaction::class)->handle(new TransactionPaymentAdded($payment));
    }

    public function test_construction_certificate_invoice_removes_generic_sale_mapping(): void
    {
        $transaction = Transaction::whereNotNull('location_id')->firstOrFail();
        $transaction->forceFill([
            'type' => 'sell',
            'status' => 'final',
            'source' => 'construction_certificate',
        ])->saveQuietly();

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->once()
            ->with($transaction->business_id, 'accounting_module')->andReturnTrue();

        $accounting = $this->mock(AccountingUtil::class);
        $accounting->shouldReceive('deleteMap')->once()->with($transaction->id, null);
        $accounting->shouldNotReceive('saveMap');

        app(MapSellTransaction::class)->handle(new SellCreatedOrModified($transaction));
    }

    public function test_standard_sale_invoice_still_reaches_generic_accounting_mapping(): void
    {
        $transaction = Transaction::whereNotNull('location_id')->firstOrFail();
        $transaction->forceFill([
            'type' => 'sell',
            'status' => 'final',
            'source' => null,
        ])->saveQuietly();

        BusinessLocation::whereKey($transaction->location_id)->update([
            'accounting_default_map' => json_encode([
                'sale' => ['deposit_to' => 201, 'payment_account' => 202],
            ]),
        ]);

        $moduleUtil = $this->mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->once()
            ->with($transaction->business_id, 'accounting_module')->andReturnTrue();

        $accounting = $this->mock(AccountingUtil::class);
        $accounting->shouldReceive('saveMap')->once()->with(
            'sell',
            $transaction->id,
            $transaction->created_by,
            $transaction->business_id,
            201,
            202
        );
        $accounting->shouldNotReceive('deleteMap');

        app(MapSellTransaction::class)->handle(new SellCreatedOrModified($transaction));
    }
}
