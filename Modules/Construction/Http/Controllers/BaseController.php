<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class BaseController extends Controller
{
    protected function normalizeLocalizedNumbers(Request $request, array $fields): void
    {
        $util = new Util();
        $values = [];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== '') {
                $values[$field] = $util->num_uf($request->input($field));
            }
        }
        $request->merge($values);
    }

    protected function normalizeBusinessDates(Request $request, array $fields): void
    {
        $util = new Util();
        $values = [];
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $date = (string) $request->input($field);
                $values[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $util->uf_date($date);
            }
        }
        $request->merge($values);
    }

    protected function businessId(): int
    {
        return (int) session('user.business_id');
    }

    protected function isAdmin(): bool
    {
        return (new Util())->is_admin(auth()->user(), $this->businessId());
    }

    protected function authorizePermission(string $permission): void
    {
        $moduleEnabled = app()->environment('local') || (bool) (new ModuleUtil())->hasThePermissionInSubscription(
            $this->businessId(),
            'construction_module'
        );

        abort_unless(
            $moduleEnabled && ($this->isAdmin() || auth()->user()->can($permission)),
            403,
            __('construction::lang.unauthorized')
        );
    }

    protected function authorizeAnyPermission(array $permissions): void
    {
        $moduleEnabled = app()->environment('local') || (bool) (new ModuleUtil())->hasThePermissionInSubscription(
            $this->businessId(),
            'construction_module'
        );

        abort_unless(
            $moduleEnabled && ($this->isAdmin() || auth()->user()->hasAnyPermission($permissions)),
            403,
            __('construction::lang.unauthorized')
        );
    }
}
