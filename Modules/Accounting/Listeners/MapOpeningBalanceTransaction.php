<?php

namespace Modules\Accounting\Listeners;

use App\Events\OpeningBalanceCreatedOrModified;
use App\Utils\ModuleUtil;
use Modules\Accounting\Utils\AccountingUtil;

class MapOpeningBalanceTransaction
{
    public function handle(OpeningBalanceCreatedOrModified $event)
    {
        $transaction = $event->transaction;
        $moduleUtil = app(ModuleUtil::class);

        if (! $moduleUtil->hasThePermissionInSubscription($transaction->business_id, 'accounting_module')) {
            return;
        }

        $this->sync($transaction);
    }

    /**
     * Public so the module update migration can backfill existing balances.
     */
    public function sync($transaction)
    {
        app(AccountingUtil::class)->syncContactOpeningBalance($transaction);
    }
}
