<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Modules\Construction\Entities\ConstructionAccountingSetting;

class SettingsController extends BaseController
{
    public function index()
    {
        $this->authorizePermission('construction.access');
        abort_unless(
            $this->isAdmin()
                || auth()->user()->can('construction.settings.manage')
                || auth()->user()->can('accounting.manage_accounts'),
            403,
            __('construction::lang.unauthorized')
        );

        $accountingConfigured = Schema::hasTable('construction_accounting_settings')
            && ConstructionAccountingSetting::where('business_id', $this->businessId())->exists();

        return view('construction::settings.index', compact('accountingConfigured'));
    }
}
