<?php

namespace Modules\Construction\Http\Controllers;

use Modules\Construction\Support\AccountingReconciliationReport;

class AccountingReconciliationController extends BaseController
{
    public function index(AccountingReconciliationReport $report)
    {
        $this->authorizePermission('construction.accounting.view');

        return view('construction::settings.accounting-reconciliation', [
            'report' => $report->build($this->businessId()),
        ]);
    }
}
