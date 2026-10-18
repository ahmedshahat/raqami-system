<?php

namespace Modules\Accounting\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\PurchaseCreatedOrModified;
use App\BusinessLocation;

class MapPurchaseTransaction
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
    public function handle(PurchaseCreatedOrModified $event)
    {
        $transaction = $event->transaction;
        $moduleUtil = app(\App\Utils\ModuleUtil::class);
        if (! $moduleUtil->hasThePermissionInSubscription($transaction->business_id, 'accounting_module')) {
            return;
        }

        $accountingUtil = app(\Modules\Accounting\Utils\AccountingUtil::class);

        if ($event->isDeleted) {
            $accountingUtil->deleteMap($event->transaction->id, null);
            return;
        }

        if ($transaction->type !== 'purchase' || $transaction->status !== 'received') {
            $accountingUtil->deleteMap($transaction->id, null);
            return;
        }

        $business_location = BusinessLocation::where('business_id', $transaction->business_id)
            ->find($transaction->location_id);
        $accounting_default_map = ! empty($business_location)
            ? json_decode($business_location->accounting_default_map ?: '[]', true)
            : [];

        $deposit_to = $accounting_default_map['purchases']['deposit_to'] ?? null;
        $payment_account = $accounting_default_map['purchases']['payment_account'] ?? null;

        if (is_null($deposit_to) || is_null($payment_account)) {
            $accountingUtil->deleteMap($transaction->id, null);
            return;
        }

        $user_id = auth()->id() ?: $transaction->created_by;
        $accountingUtil->saveMap('purchase', $transaction->id, $user_id, $transaction->business_id,
            $deposit_to, $payment_account);
    }
}
