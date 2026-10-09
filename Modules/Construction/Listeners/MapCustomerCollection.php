<?php

namespace Modules\Construction\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Construction\Support\ConstructionAccountingPoster;

class MapCustomerCollection
{
    public function handle($event): void
    {
        $payment = $event->transactionPayment;

        DB::transaction(function () use ($event, $payment) {
            $poster = app(ConstructionAccountingPoster::class);
            if (! empty($event->isDeleted)) {
                $poster->reverseCustomerCollection($payment);
                return;
            }

            $poster->syncCustomerCollection($payment);
            // The Construction entry is authoritative for certificate collections.
            // Remove the generic Accounting payment mapping if its listener ran first.
            app(\Modules\Accounting\Utils\AccountingUtil::class)->deleteMap(null, $payment->id);
        });
    }
}
