<?php

namespace Modules\Accounting\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\BusinessLocation;
use App\Contact;
use App\Transaction;

class MapPaymentTransaction
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
    public function handle($event)
    {
        $payment = $event->transactionPayment;
        $moduleUtil = app(\App\Utils\ModuleUtil::class);
        if (! $moduleUtil->hasThePermissionInSubscription($payment->business_id, 'accounting_module')) {
            return;
        }

        $accountingUtil = app(\Modules\Accounting\Utils\AccountingUtil::class);
        $transaction = empty($payment->transaction_id)
            ? null
            : Transaction::where('business_id', $payment->business_id)->find($payment->transaction_id);
        $isConstructionCollection = $transaction
            && $transaction->type === 'sell'
            && $transaction->source === 'construction_certificate';

        // Construction owns the complete accounting entry for certificate collections.
        // Always remove only a possible legacy generic payment mapping, regardless of
        // listener execution order, and leave the Construction mapping untouched.
        if ($isConstructionCollection) {
            $accountingUtil->deleteMap(null, $payment->id);
            return;
        }

        if (isset($event->isDeleted) && $event->isDeleted) {
            $accountingUtil->deleteMap(null, $payment->id);
            return;
        }

        if (! empty($transaction)) {
            if ($transaction->type === 'purchase' && $transaction->status === 'received') {
                $type = 'purchase_payment';
            } elseif ($transaction->type === 'sell' && $transaction->status === 'final') {
                $type = 'sell_payment';
            } elseif ($transaction->type === 'opening_balance') {
                $contact_type = Contact::where('business_id', $payment->business_id)
                    ->where('id', $transaction->contact_id)
                    ->value('type');
                $type = $contact_type === 'supplier' ? 'purchase_payment' : 'sell_payment';
            } else {
                $accountingUtil->deleteMap(null, $payment->id);
                return;
            }

            $business_location = BusinessLocation::where('business_id', $transaction->business_id)
                ->find($transaction->location_id);
        } else {
            // Contact-level receipts/payments (including opening-balance
            // collections) use a parent payment without transaction_id.
            $contact_type = Contact::where('business_id', $payment->business_id)
                ->where('id', $payment->payment_for)
                ->value('type');
            if (empty($contact_type)) {
                $accountingUtil->deleteMap(null, $payment->id);
                return;
            }

            $type = $contact_type === 'supplier' ? 'purchase_payment' : 'sell_payment';
            $business_location = BusinessLocation::where('business_id', $payment->business_id)
                ->first();
        }
        $accounting_default_map = ! empty($business_location)
            ? json_decode($business_location->accounting_default_map ?: '[]', true)
            : [];

        //check if default map is set or not, if set the proceed.
        $deposit_to = isset($accounting_default_map[$type]['deposit_to']) ? $accounting_default_map[$type]['deposit_to'] : null;
        $payment_account = isset($accounting_default_map[$type]['payment_account']) ? $accounting_default_map[$type]['payment_account'] : null;

        if (is_null($deposit_to) || is_null($payment_account)) {
            $accountingUtil->deleteMap(null, $payment->id);
            return;
        }

        $user_id = auth()->id() ?: $payment->created_by;
        $accountingUtil->saveMap($type, $payment->id, $user_id, $payment->business_id,
            $deposit_to, $payment_account);
    }
}
