<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function modifyAdminMenu(): void
    {
        $businessId = (int) session('user.business_id');
        $isAdmin = (new Util())->is_admin(auth()->user(), $businessId);
        $isEnabled = app()->environment('local') || (bool) (new ModuleUtil())->hasThePermissionInSubscription(
            $businessId,
            'construction_module'
        );

        if (! $isEnabled || (! $isAdmin && ! auth()->user()->can('construction.access'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('construction::lang.construction'),
                function ($sub) {
                    $sub->url(
                        route('construction.overview.index'),
                        __('construction::lang.tab_overview'),
                        ['icon' => '', 'active' => request()->routeIs('construction.overview.*') || request()->routeIs('construction.dashboard')]
                    );
                    $sub->url(
                        route('construction.quotes.index'),
                        __('construction::lang.tab_quotes'),
                        ['icon' => '', 'active' => request()->routeIs('construction.quotes.*')]
                    );
                    $sub->url(
                        route('construction.projects.index'),
                        __('construction::lang.nav_projects_contracts'),
                        [
                            'icon' => '',
                            'active' => (request()->routeIs('construction.projects.*')
                                && ! request()->routeIs('construction.projects.measurements.*')
                                && ! request()->routeIs('construction.projects.certificates.*'))
                                || request()->routeIs('construction.project-items.*')
                                || request()->routeIs('construction.contracts.*'),
                        ]
                    );
                    $sub->url(
                        route('construction.measurements.index'),
                        __('construction::lang.nav_measurements_certificates'),
                        [
                            'icon' => '',
                            'active' => request()->routeIs('construction.measurements.*')
                                || request()->routeIs('construction.ipcs.*')
                                || request()->routeIs('construction.projects.measurements.*')
                                || request()->routeIs('construction.projects.certificates.*'),
                        ]
                    );
                    $sub->url(
                        route('construction.costs.index'),
                        __('construction::lang.nav_costs'),
                        ['icon' => '', 'active' => request()->routeIs('construction.costs.*') || request()->routeIs('construction.materials.*') || request()->routeIs('construction.labor.*') || request()->routeIs('construction.cost-codes.*')]
                    );
                    $sub->url(
                        route('construction.subcontractors.index'),
                        __('construction::lang.tab_subcontractors'),
                        ['icon' => '', 'active' => request()->routeIs('construction.subcontractors.*')]
                    );
                    $sub->url(
                        route('construction.reports.index'),
                        __('construction::lang.nav_reports'),
                        ['icon' => '', 'active' => request()->routeIs('construction.reports.*')]
                    );
                },
                [
                    'icon' => 'fa fa-hard-hat',
                    'active' => request()->segment(1) === 'construction',
                ]
            )->order(7);
        });
    }

    public function user_permissions(): array
    {
        return [
            [
                'value' => 'construction.access',
                'label' => __('construction::lang.access_module'),
                'default' => false,
            ],
            [
                'value' => 'construction.project.view',
                'label' => __('construction::lang.view_projects'),
                'default' => false,
            ],
            [
                'value' => 'construction.project.create',
                'label' => __('construction::lang.create_project'),
                'default' => false,
            ],
            [
                'value' => 'construction.project.update',
                'label' => __('construction::lang.update_project'),
                'default' => false,
            ],
            [
                'value' => 'construction.project.delete',
                'label' => __('construction::lang.delete_project'),
                'default' => false,
            ],
            [
                'value' => 'construction.contract.view',
                'label' => __('construction::lang.view_contracts'),
                'default' => false,
            ],
            [
                'value' => 'construction.contract.manage',
                'label' => __('construction::lang.manage_contracts'),
                'default' => false,
            ],
            [
                'value' => 'construction.contract.approve',
                'label' => __('construction::lang.approve_contracts'),
                'default' => false,
            ],
            [
                'value' => 'construction.boq.view',
                'label' => __('construction::lang.view_boq'),
                'default' => false,
            ],
            [
                'value' => 'construction.boq.manage',
                'label' => __('construction::lang.manage_boq'),
                'default' => false,
            ],
            [
                'value' => 'construction.boq.approve',
                'label' => __('construction::lang.approve_boq'),
                'default' => false,
            ],
            [
                'value' => 'construction.measurement.view',
                'label' => __('construction::lang.view_measurements'),
                'default' => false,
            ],
            [
                'value' => 'construction.measurement.manage',
                'label' => __('construction::lang.manage_measurements'),
                'default' => false,
            ],
            [
                'value' => 'construction.measurement.approve',
                'label' => __('construction::lang.approve_measurements'),
                'default' => false,
            ],
            [
                'value' => 'construction.certificate.view',
                'label' => __('construction::lang.view_certificates'),
                'default' => false,
            ],
            [
                'value' => 'construction.certificate.manage',
                'label' => __('construction::lang.manage_certificates'),
                'default' => false,
            ],
            [
                'value' => 'construction.certificate.approve',
                'label' => __('construction::lang.approve_certificates'),
                'default' => false,
            ],
            [
                'value' => 'construction.material.view',
                'label' => __('construction::lang.view_material_documents'),
                'default' => false,
            ],
            [
                'value' => 'construction.material.manage',
                'label' => __('construction::lang.manage_material_documents'),
                'default' => false,
            ],
            [
                'value' => 'construction.material.approve',
                'label' => __('construction::lang.approve_material_documents'),
                'default' => false,
            ],
            [
                'value' => 'construction.labor.view',
                'label' => __('construction::lang.view_labor_sheets'),
                'default' => false,
            ],
            [
                'value' => 'construction.labor.manage',
                'label' => __('construction::lang.manage_labor_sheets'),
                'default' => false,
            ],
            [
                'value' => 'construction.labor.approve',
                'label' => __('construction::lang.approve_labor_sheets'),
                'default' => false,
            ],
            [
                'value' => 'construction.subcontract.view',
                'label' => __('construction::lang.view_subcontracts'),
                'default' => false,
            ],
            [
                'value' => 'construction.subcontract.manage',
                'label' => __('construction::lang.manage_subcontracts'),
                'default' => false,
            ],
            [
                'value' => 'construction.subcontract.approve',
                'label' => __('construction::lang.approve_subcontracts'),
                'default' => false,
            ],
        ];
    }

    public function superadmin_package(): array
    {
        return [
            [
                'name' => 'construction_module',
                'label' => __('construction::lang.construction_module'),
                'default' => false,
            ],
        ];
    }
}
