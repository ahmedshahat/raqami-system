<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\HomeDashboardUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

/**
 * The redesigned home dashboard (Raqmi design system). It runs next to the
 * old home (HomeController@index) until it is approved as the main home.
 */
class HomeDashboardController extends Controller
{
    protected $businessUtil;

    protected $dashboardUtil;

    protected $moduleUtil;

    public function __construct(BusinessUtil $businessUtil, HomeDashboardUtil $dashboardUtil, ModuleUtil $moduleUtil)
    {
        $this->businessUtil = $businessUtil;
        $this->dashboardUtil = $dashboardUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->user_type == 'user_customer') {
            return redirect()->action([\Modules\Crm\Http\Controllers\DashboardController::class, 'index']);
        }

        $business_id = $request->session()->get('user.business_id');
        $is_admin = $this->businessUtil->is_admin($user);
        $can_view_data = $user->can('dashboard.data');
        $can_view_sales = $user->can('sell.view') || $user->can('direct_sell.view');

        if (! $can_view_data) {
            return view('home_v2.index', compact('is_admin', 'can_view_data', 'can_view_sales'));
        }

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();
        $location_id = $request->input('location_id');
        if (! empty($location_id) && ! array_key_exists($location_id, $all_locations)) {
            $location_id = null;
        }
        $permitted_locations = $user->permitted_locations();

        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
        $period = $this->dashboardUtil->resolvePeriod($request->input('period', 'this_month'), $request->input('start'), $request->input('end'), $fy);
        $buckets = $this->dashboardUtil->buckets($period);
        $common_settings = session('business.common_settings', []) ?: [];

        $current = $this->dashboardUtil->transactionSeries($business_id, $period, $period['start'], $period['end'], $location_id, $permitted_locations);
        $previous = $this->dashboardUtil->transactionSeries($business_id, $period, $period['prev_start'], $period['prev_end'], $location_id, $permitted_locations);
        $sum = function ($series, $type) {
            return array_sum($series[$type] ?? []);
        };
        $line = function ($values) use ($buckets) {
            $out = [];
            foreach (array_keys($buckets) as $key) {
                $out[] = round((float) ($values[$key] ?? 0), 2);
            }

            return $out;
        };

        $kpis = [];
        $secondary = [];
        $payment_methods = [];
        $profit_series = [];
        if ($is_admin) {
            $profit_current = $this->dashboardUtil->grossProfitSeries($business_id, $period, $period['start'], $period['end'], $location_id, $permitted_locations);
            $profit_previous = $this->dashboardUtil->grossProfitSeries($business_id, $period, $period['prev_start'], $period['prev_end'], $location_id, $permitted_locations);
            $collections = $this->dashboardUtil->collections($business_id, $period, $period['start'], $period['end'], $location_id, $permitted_locations);
            $collections_previous = $this->dashboardUtil->collections($business_id, $period, $period['prev_start'], $period['prev_end'], $location_id, $permitted_locations);
            $profit_series = $line($profit_current);
            $payment_methods = $collections['by_method'];

            $sales = $sum($current, 'sell');
            $profit = array_sum($profit_current);
            $kpis = [
                'sales' => ['value' => $sales, 'prev' => $sum($previous, 'sell'), 'series' => $line($current['sell'] ?? []), 'icon' => 'trend', 'tone' => 'brand', 'color' => 1,
                    'meta' => __('home_v2.invoices_count', ['count' => $sum($current, 'sell_count')])],
                'gross_profit' => ['value' => $profit, 'prev' => array_sum($profit_previous), 'series' => $profit_series, 'icon' => 'coins', 'tone' => 'success', 'color' => 3,
                    'meta' => __('home_v2.margin', ['value' => $sales > 0 ? round($profit / $sales * 100, 1) : 0])],
                'collected' => ['value' => array_sum($collections['by_bucket']), 'prev' => array_sum($collections_previous['by_bucket']), 'series' => $line($collections['by_bucket']), 'icon' => 'wallet', 'tone' => 'info', 'color' => 5,
                    'meta' => __('home_v2.collected_meta')],
                'expenses' => ['value' => $sum($current, 'expense'), 'prev' => $sum($previous, 'expense'), 'series' => $line($current['expense'] ?? []), 'icon' => 'receipt', 'tone' => 'warning', 'color' => 2, 'inverse' => true,
                    'meta' => __('home_v2.expenses_count', ['count' => $sum($current, 'expense_count')])],
            ];

            $outstanding = $this->dashboardUtil->outstanding($business_id, $location_id, $permitted_locations);
            $secondary = [
                'purchases' => ['value' => $sum($current, 'purchase'), 'icon' => 'cart', 'tone' => 'neutral'],
                'sell_returns' => ['value' => $sum($current, 'sell_return'), 'icon' => 'undo', 'tone' => 'danger'],
                'receivable' => ['value' => $outstanding['receivable'], 'icon' => 'users', 'tone' => 'info'],
                'payable' => ['value' => $outstanding['payable'], 'icon' => 'building', 'tone' => 'warning'],
            ];
        }

        $chart = [];
        $top_products = collect();
        if ($can_view_sales) {
            $chart = [
                'sales' => $line($current['sell'] ?? []),
                'purchases' => $is_admin ? $line($current['purchase'] ?? []) : [],
                'expenses' => $is_admin ? $line($current['expense'] ?? []) : [],
                'profit' => $profit_series,
            ];
            $top_products = $this->dashboardUtil->topProducts($business_id, $period['start'], $period['end'], $location_id, $permitted_locations);
        }

        $attention = $this->dashboardUtil->attention($business_id, $location_id, $permitted_locations, $common_settings);
        $payment_types = $this->businessUtil->payment_types(null, true, $business_id);

        $aging = $is_admin ? $this->dashboardUtil->receivablesAging($business_id, $location_id, $permitted_locations) : null;
        $heatmap = $can_view_sales ? $this->dashboardUtil->salesHeatmap($business_id, $period['end'], $location_id, $permitted_locations) : null;
        $forecast = $user->can('stock_report.view') ? $this->dashboardUtil->stockForecast($business_id, $location_id, $permitted_locations) : null;
        $module_widgets = $this->dashboardUtil->moduleWidgets($this->moduleUtil, $business_id, $location_id);

        return view('home_v2.index', compact(
            'is_admin', 'can_view_data', 'can_view_sales', 'all_locations', 'location_id',
            'period', 'buckets', 'kpis', 'secondary', 'chart', 'top_products', 'payment_methods', 'payment_types', 'attention',
            'aging', 'heatmap', 'forecast', 'module_widgets'
        ));
    }
}
