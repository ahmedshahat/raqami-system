<?php

namespace Modules\Accounting\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\BusinessLocation;

class MapExpenseTransactions
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
        $expense = $event->expense;
        $moduleUtil = app(\App\Utils\ModuleUtil::class);
        if (! $moduleUtil->hasThePermissionInSubscription($expense->business_id, 'accounting_module')) {
            return;
        }

        $accountingUtil = app(\Modules\Accounting\Utils\AccountingUtil::class);

        if (isset($event->isDeleted) && $event->isDeleted) {
            $accountingUtil->deleteMap($expense->id, null);
            return;
        }

        // The Construction cancellation workflow owns the balancing journal for
        // this generated expense refund. It exists so native expense reports net
        // the cancelled document to zero and must never create another GL entry.
        if ($expense->source === 'construction_expense_cancellation') {
            $accountingUtil->deleteMap($expense->id, null);
            return;
        }

        if ($expense->type !== 'expense') {
            $accountingUtil->deleteMap($expense->id, null);
            return;
        }

        $business_location = BusinessLocation::where('business_id', $expense->business_id)
            ->find($expense->location_id);
        $accounting_default_map = ! empty($business_location)
            ? json_decode($business_location->accounting_default_map ?: '[]', true)
            : [];
        // if expense category is selected
        if(!empty($event->expense->expense_category_id)){
            
                $deposit_to = isset($accounting_default_map['expense_'.$event->expense->expense_category_id]['deposit_to']) ? $accounting_default_map['expense_'.$event->expense->expense_category_id]['deposit_to'] : null;

                $payment_account = isset($accounting_default_map['expense_'.$event->expense->expense_category_id]['payment_account']) ? $accounting_default_map['expense_'.$event->expense->expense_category_id]['payment_account'] : null;
            // if expense category is selected but value is null 
            if(is_null($deposit_to) || is_null($payment_account)){
                $deposit_to = isset($accounting_default_map['expense']['deposit_to']) ? $accounting_default_map['expense']['deposit_to'] : null;
                $payment_account = isset($accounting_default_map['expense']['payment_account']) ? $accounting_default_map['expense']['payment_account'] : null;
            }
               
        }else{

            $deposit_to = isset($accounting_default_map['expense']['deposit_to']) ? $accounting_default_map['expense']['deposit_to'] : null;
            $payment_account = isset($accounting_default_map['expense']['payment_account']) ? $accounting_default_map['expense']['payment_account'] : null;
        }

        if (is_null($deposit_to) || is_null($payment_account)) {
            $accountingUtil->deleteMap($expense->id, null);
            return;
        }

        $user_id = auth()->id() ?: $expense->created_by;
        $accountingUtil->saveMap('expense', $expense->id, $user_id, $expense->business_id,
            $deposit_to, $payment_account);
    }
}
