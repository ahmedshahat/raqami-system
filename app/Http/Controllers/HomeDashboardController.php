<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\HomeDashboardUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

/**
 * The home dashboard (Raqmi design system). HomeController@index (/home)
 * serves it, unless the user switched back to the classic home for himself
 * (users.use_classic_home).
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
        $prefs = $this->dashboardUtil->userLayout($user);
        $show = function ($key) use ($prefs) {
            return ! in_array($key, $prefs['hidden']);
        };

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
        if ($is_admin && ($show('kpis') || $show('secondary') || $show('chart') || $show('payment_methods'))) {
            $profit_current = $this->dashboardUtil->grossProfitSeries($business_id, $period, $period['start'], $period['end'], $location_id, $permitted_locations);
            $profit_previous = $this->dashboardUtil->grossProfitSeries($business_id, $period, $period['prev_start'], $period['prev_end'], $location_id, $permitted_locations);
            $collections = $this->dashboardUtil->collections($business_id, $period, $period['start'], $period['end'], $location_id, $permitted_locations);
            $collections_previous = $this->dashboardUtil->collections($business_id, $period, $period['prev_start'], $period['prev_end'], $location_id, $permitted_locations);
            $profit_series = $line($profit_current);
            $payment_methods = $collections['by_method'];

            $sales = $sum($current, 'sell');
            $profit = array_sum($profit_current);
            $kpis = [
                'sales' => ['value' => $sales, 'prev' => $sum($previous, 'sell'), 'icon' => 'trend', 'tone' => 'info',
                    'meta' => __('home_v2.invoices_count', ['count' => $sum($current, 'sell_count')])],
                'gross_profit' => ['value' => $profit, 'prev' => array_sum($profit_previous), 'icon' => 'bars', 'tone' => 'success',
                    'meta' => __('home_v2.margin', ['value' => $sales > 0 ? round($profit / $sales * 100, 1) : 0])],
                'collected' => ['value' => array_sum($collections['by_bucket']), 'prev' => array_sum($collections_previous['by_bucket']), 'icon' => 'check-circle', 'tone' => 'brand',
                    'meta' => __('home_v2.collected_meta')],
                'expenses' => ['value' => $sum($current, 'expense'), 'prev' => $sum($previous, 'expense'), 'icon' => 'receipt', 'tone' => 'warning', 'inverse' => true,
                    'meta' => __('home_v2.expenses_count', ['count' => $sum($current, 'expense_count')])],
            ];

            // كل كارت بيفتح شاشة تفاصيله على نفس الفترة (rr_from/rr_to بيضبطوا فلتر التاريخ هناك)، لو اليوزر مسموح له بيها
            $range = ['rr_from' => $period['start'], 'rr_to' => $period['end']];
            $links = [
                'sales' => $can_view_sales ? action([\App\Http\Controllers\SellController::class, 'index'], $range) : null,
                'gross_profit' => $user->can('profit_loss_report.view') ? action([\App\Http\Controllers\ReportController::class, 'getProfitLoss'], $range) : null,
                'collected' => $user->can('purchase_n_sell_report.view') ? action([\App\Http\Controllers\ReportController::class, 'sellPaymentReport'], $range) : null,
                'expenses' => ($user->can('all_expense.access') || $user->can('view_own_expense')) ? action([\App\Http\Controllers\ExpenseController::class, 'index'], $range) : null,
            ];
            foreach ($links as $key => $url) {
                $kpis[$key]['url'] = $url;
            }

            $outstanding = $show('secondary') ? $this->dashboardUtil->outstanding($business_id, $location_id, $permitted_locations) : ['receivable' => 0, 'payable' => 0, 'receivable_opening' => 0, 'payable_opening' => 0, 'receivable_credit' => 0, 'payable_credit' => 0];
            $secondary = [
                'purchases' => ['value' => $sum($current, 'purchase'), 'icon' => 'cart', 'tone' => 'neutral'],
                'sell_returns' => ['value' => $sum($current, 'sell_return'), 'icon' => 'undo', 'tone' => 'danger'],
                'receivable' => ['value' => $outstanding['receivable'], 'opening' => $outstanding['receivable_opening'], 'credit' => $outstanding['receivable_credit'], 'icon' => 'users', 'tone' => 'info'],
                'payable' => ['value' => $outstanding['payable'], 'opening' => $outstanding['payable_opening'], 'credit' => $outstanding['payable_credit'], 'icon' => 'building', 'tone' => 'warning'],
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
            $top_products = ! $show('top_products') ? collect() : $this->dashboardUtil->topProducts($business_id, $period['start'], $period['end'], $location_id, $permitted_locations);
        }

        $attention = ! $show('attention') ? [] : $this->dashboardUtil->attention($business_id, $location_id, $permitted_locations, $common_settings);
        $payment_types = $this->businessUtil->payment_types(null, true, $business_id);

        $aging = $is_admin && $show('aging') ? $this->dashboardUtil->receivablesAging($business_id, $location_id, $permitted_locations) : null;
        $heatmap = $can_view_sales && $show('heatmap') ? $this->dashboardUtil->salesHeatmap($business_id, $period['end'], $location_id, $permitted_locations) : null;
        $can_view_stock = $user->can('stock_report.view');
        $forecast = $can_view_stock && $show('forecast') ? $this->dashboardUtil->stockForecast($business_id, $location_id, $permitted_locations) : null;
        $module_widgets = ! $show('modules') ? [] : $this->dashboardUtil->moduleWidgets($this->moduleUtil, $business_id, $location_id);

        // أقسام متاحة لليوزر حسب صلاحياته (بتظهر في نافذة التخصيص حتى لو مخفية)
        $available = [
            'kpis' => $is_admin, 'secondary' => $is_admin, 'chart' => $can_view_sales, 'top_products' => $can_view_sales,
            'forecast' => $can_view_stock, 'attention' => true, 'aging' => $is_admin, 'payment_methods' => $is_admin,
            'heatmap' => $can_view_sales, 'modules' => true,
        ];
        $layout = $this->dashboardUtil->renderLayout($prefs, $available);

        return view('home_v2.index', compact(
            'is_admin', 'can_view_data', 'can_view_sales', 'all_locations', 'location_id',
            'period', 'buckets', 'kpis', 'secondary', 'chart', 'top_products', 'payment_methods', 'payment_types', 'attention',
            'aging', 'heatmap', 'forecast', 'module_widgets', 'prefs', 'available', 'layout'
        ));
    }

    /**
     * Saves the user's dashboard layout (order per zone + visible sections) or resets it.
     */
    public function saveLayout(Request $request)
    {
        $user = auth()->user();
        if ($request->boolean('reset')) {
            $this->dashboardUtil->saveUserLayout($user, null);
        } else {
            $this->dashboardUtil->saveUserLayout($user, (array) $request->input('order', []), (array) $request->input('visible', []));
        }

        return redirect()->to(url()->previous() ?: route('home'))
            ->with('status', ['success' => 1, 'msg' => __('home_v2.layout_saved')]);
    }

    /**
     * Switches the current user between the new home and the classic one (for himself only).
     */
    public function switchHome(Request $request)
    {
        $data = $request->validate(['version' => ['required', 'in:new,classic']]);

        $user = auth()->user();
        $user->use_classic_home = $data['version'] == 'classic';
        $user->save();

        return redirect()->route('home');
    }
}
