<?php

namespace Modules\Accounting\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\SellCreatedOrModified;
use App\BusinessLocation;

class MapSellTransaction
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(SellCreatedOrModified $event)
    {
        $transaction = $event->transaction;
        $moduleUtil = app(\App\Utils\ModuleUtil::class);
        if (! $moduleUtil->hasThePermissionInSubscription($transaction->business_id, 'accounting_module')) {
            return;
        }

        $accountingUtil = app(\Modules\Accounting\Utils\AccountingUtil::class);

        if ($event->isDeleted) {
            // Some callers may dispatch a deletion event even when deletion
            // was rejected (for example, when a return exists). Only remove
            // the accounting map after the source transaction is truly gone.
            $transactionStillExists = \App\Transaction::where('id', $transaction->id)
                ->where('business_id', $transaction->business_id)
                ->exists();
            if ($transactionStillExists) {
                return;
            }

            $accountingUtil->deleteMap($event->transaction->id, null);
            return;
        }

        // Construction posts the complete customer-certificate entry itself because
        // the invoice total alone does not represent retention, advance recovery,
        // deductions and tax. Remove a possible legacy generic sale map as well so
        // changing listener order or enabling Accounting later cannot duplicate it.
        if ($transaction->type === 'sell' && $transaction->source === 'construction_certificate') {
            $accountingUtil->deleteMap($transaction->id, null);
            return;
        }

        if ($transaction->type !== 'sell' || $transaction->status !== 'final') {
            $accountingUtil->deleteMap($transaction->id, null);
            return;
        }

        $business_location = BusinessLocation::where('business_id', $transaction->business_id)
            ->find($transaction->location_id);
        $accounting_default_map = ! empty($business_location)
            ? json_decode($business_location->accounting_default_map ?: '[]', true)
            : [];

        $deposit_to = $accounting_default_map['sale']['deposit_to'] ?? null;
        $payment_account = $accounting_default_map['sale']['payment_account'] ?? null;

        if (is_null($deposit_to) || is_null($payment_account)) {
            $accountingUtil->deleteMap($transaction->id, null);
            return;
        }

        $user_id = auth()->id() ?: $transaction->created_by;
        $accountingUtil->saveMap('sell', $transaction->id, $user_id, $transaction->business_id,
            $deposit_to, $payment_account);
    }
}
