<?php

namespace App\Listeners;

use App\AccountTransaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;

class DeleteAccountTransaction
{
    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        //Add contact advance if exists
        if ($event->transactionPayment->method == 'advance') {
            $this->transactionUtil->updateContactBalance($event->transactionPayment->payment_for, $event->transactionPayment->amount);
        }

        if (! $this->moduleUtil->isModuleEnabled('account')) {
            return true;
        }

        // The payment id is the stable link to its account transaction. The
        // payment's account_id can be changed or cleared before the delete
        // event is dispatched, which used to leave an orphan amount in the
        // account ledger after its sale invoice had been deleted.
        AccountTransaction::where('transaction_payment_id', $event->transactionPayment->id)
                        ->delete();
    }
}
