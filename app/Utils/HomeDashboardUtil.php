<?php

namespace App\Utils;

use App\Cheque;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data for the redesigned home dashboard (home/v2): KPIs vs. the previous
 * period, a sales / purchases / expenses / gross profit series, top products,
 * payment methods and the "needs attention" counters.
 *
 * Totals follow the same rules as the core totals (TransactionUtil):
 * sells are final only, purchases / returns / expenses have no status filter.
 */
class HomeDashboardUtil
{
    /**
     * Dashboard sections by zone, in their default order. Users can hide
     * sections and reorder them inside their zone (users.dashboard_layout).
     */
    public const SECTIONS = [
        'top' => ['kpis', 'secondary'],
        'main' => ['chart', 'attention'],
        'cards' => ['top_products', 'aging', 'payment_methods', 'forecast'],
        'bottom' => ['heatmap', 'modules'],
    ];

    /** Main-zone sections that take twice the width of the others in their row. */
    public const WIDE_SECTIONS = ['chart'];

    /** The cards zone has this many columns; extra cards stack in the last one. */
    public const CARD_COLUMNS = 3;

    protected $transactionUtil;

    protected $productUtil;

    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Resolves a period preset (or custom range) into the current range,
     * the previous range of the same length and the chart bucket size.
     */
    public function resolvePeriod($preset, $start = null, $end = null, $fy = null)
    {
        $today = Carbon::today();

        switch ($preset) {
            case 'today':
                $from = $today->copy();
                $to = $today->copy();
                break;
            case '7_days':
                $from = $today->copy()->subDays(6);
                $to = $today->copy();
                break;
            case 'last_month':
                $from = $today->copy()->subMonthNoOverflow()->startOfMonth();
                $to = $from->copy()->endOfMonth()->startOfDay();
                break;
            case 'this_fy':
                $from = Carbon::parse($fy['start'])->startOfDay();
                $to = $today->copy();
                break;
            case 'custom':
                try {
                    $from = Carbon::parse($start)->startOfDay();
                    $to = Carbon::parse($end)->startOfDay();
                } catch (\Exception $e) {
                    return $this->resolvePeriod('this_month');
                }
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                break;
            default:
                $preset = 'this_month';
                $from = $today->copy()->startOfMonth();
                $to = $today->copy();
        }

        $days = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        if ($days == 1) {
            $bucket = 'hour';
        } elseif ($days <= 62) {
            $bucket = 'day';
        } else {
            $bucket = 'month';
        }

        return [
            'preset' => $preset,
            'start' => $from->toDateString(),
            'end' => $to->toDateString(),
            'prev_start' => $prevFrom->toDateString(),
            'prev_end' => $prevTo->toDateString(),
            'days' => $days,
            'bucket' => $bucket,
        ];
    }

    /**
     * Bucket keys (in order) with their axis ('short') and tooltip ('full') labels.
     */
    public function buckets($period)
    {
        $buckets = [];
        if ($period['bucket'] == 'hour') {
            for ($h = 0; $h < 24; $h++) {
                $buckets[sprintf('%02d', $h)] = ['short' => sprintf('%02d:00', $h), 'full' => sprintf('%02d:00', $h)];
            }

            return $buckets;
        }

        $cursor = Carbon::parse($period['start']);
        $end = Carbon::parse($period['end']);
        if ($period['bucket'] == 'month') {
            $cursor->startOfMonth();
            while ($cursor->lte($end)) {
                $buckets[$cursor->format('Y-m')] = ['short' => $cursor->translatedFormat('M'), 'full' => $cursor->translatedFormat('F Y')];
                $cursor->addMonth();
            }
        } else {
            while ($cursor->lte($end)) {
                $buckets[$cursor->toDateString()] = ['short' => $cursor->translatedFormat('j'), 'full' => $cursor->translatedFormat('l j F Y')];
                $cursor->addDay();
            }
        }

        return $buckets;
    }

    /**
     * Sums of sells / purchases / expenses / returns grouped by bucket.
     */
    public function transactionSeries($business_id, $period, $start, $end, $location_id, $permitted_locations)
    {
        $format = $this->bucketFormat($period['bucket']);

        $query = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['sell', 'purchase', 'expense', 'sell_return', 'purchase_return'])
            ->where(function ($q) {
                $q->where('type', '!=', 'sell')->orWhere('status', 'final');
            })
            ->whereDate('transaction_date', '>=', $start)
            ->whereDate('transaction_date', '<=', $end)
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '$format') as bucket"),
                'type',
                DB::raw('SUM(final_total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('bucket', 'type');

        $this->filterLocation($query, 'location_id', $location_id, $permitted_locations);

        $series = [];
        foreach ($query->get() as $row) {
            $series[$row->type][$row->bucket] = (float) $row->total;
            $series[$row->type.'_count'][$row->bucket] = (int) $row->count;
        }

        return $series;
    }

    /**
     * Gross profit grouped by bucket (same expression as the profit / loss report).
     */
    public function grossProfitSeries($business_id, $period, $start, $end, $location_id, $permitted_locations)
    {
        $format = $this->bucketFormat($period['bucket']);

        $rows = $this->transactionUtil
            ->grossProfitQuery($business_id, $start, $end, $location_id, null, $this->permitted($permitted_locations))
            ->addSelect(DB::raw("DATE_FORMAT(sale.transaction_date, '$format') as bucket"))
            ->groupBy('bucket')
            ->get();

        return $rows->pluck('gross_profit', 'bucket')->map(function ($value) {
            return (float) $value;
        })->all();
    }

    /**
     * Customer payments received on sells, grouped by bucket and by method.
     */
    public function collections($business_id, $period, $start, $end, $location_id, $permitted_locations)
    {
        $format = $this->bucketFormat($period['bucket']);

        $base = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->whereDate('transaction_payments.paid_on', '>=', $start)
            ->whereDate('transaction_payments.paid_on', '<=', $end);

        $this->filterLocation($base, 't.location_id', $location_id, $permitted_locations);

        $amount = 'SUM(IF(transaction_payments.is_return = 1, -1 * transaction_payments.amount, transaction_payments.amount))';

        $byBucket = (clone $base)
            ->select(DB::raw("DATE_FORMAT(transaction_payments.paid_on, '$format') as bucket"), DB::raw("$amount as total"))
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(function ($value) {
                return (float) $value;
            })->all();

        $byMethod = (clone $base)
            ->select('transaction_payments.method', DB::raw("$amount as total"))
            ->groupBy('transaction_payments.method')
            ->orderByDesc('total')
            ->pluck('total', 'method')
            ->map(function ($value) {
                return (float) $value;
            })->filter(function ($value) {
                return $value > 0;
            })->all();

        return ['by_bucket' => $byBucket, 'by_method' => $byMethod];
    }

    /**
     * Best selling products in the period by net sales value.
     */
    public function topProducts($business_id, $start, $end, $location_id, $permitted_locations, $limit = 5)
    {
        $query = TransactionSellLine::join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
            ->join('products as p', 'transaction_sell_lines.product_id', '=', 'p.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('transaction_sell_lines.parent_sell_line_id')
            ->whereDate('t.transaction_date', '>=', $start)
            ->whereDate('t.transaction_date', '<=', $end)
            ->select(
                'p.id',
                'p.name',
                DB::raw('SUM(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as qty'),
                DB::raw('SUM((transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as total')
            )
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total')
            ->limit($limit);

        $this->filterLocation($query, 't.location_id', $location_id, $permitted_locations);

        return $query->get()->filter(function ($row) {
            return $row->total > 0;
        })->values();
    }

    /**
     * Outstanding customer / supplier balances (all time, not limited to the period).
     */
    public function outstanding($business_id, $location_id, $permitted_locations)
    {
        $permitted = $this->permitted($permitted_locations);
        $sells = $this->transactionUtil->getSellTotals($business_id, null, null, $location_id, null, $permitted);
        $purchases = $this->transactionUtil->getPurchaseTotals($business_id, null, null, $location_id, null, $permitted);
        $ledger = $this->transactionUtil->getTotalLedgerDiscount($business_id);

        return [
            'receivable' => (float) $sells['invoice_due'] - (float) $ledger['total_sell_discount'],
            'payable' => (float) $purchases['purchase_due'] - (float) $ledger['total_purchase_discount'],
        ];
    }

    /**
     * Counters for the "needs attention" list. Each item is only built when the
     * user may open the screen it links to.
     */
    public function attention($business_id, $location_id, $permitted_locations, $common_settings)
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();
        $items = [];
        $dueDate = "DATE_ADD(transactions.transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY)";
        $paid = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id)';

        if ($user->can('sell.view') || $user->can('direct_sell.view')) {
            $query = Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->where('transactions.payment_status', '!=', 'paid')
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("DATE($dueDate) < ?", [$today]);
            $this->filterLocation($query, 'transactions.location_id', $location_id, $permitted_locations);
            $row = $query->select(DB::raw('COUNT(*) as count'), DB::raw("SUM(transactions.final_total - $paid) as amount"))->first();
            $items[] = [
                'key' => 'overdue_sales', 'icon' => 'receipt', 'tone' => 'danger',
                'count' => (int) $row->count, 'amount' => (float) $row->amount,
                'url' => action([\App\Http\Controllers\SellController::class, 'index']),
            ];
        }

        if ($user->can('purchase.view')) {
            $query = Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.type', 'purchase')
                ->where('transactions.payment_status', '!=', 'paid')
                ->whereRaw("DATEDIFF($dueDate, ?) <= 7", [$today]);
            $this->filterLocation($query, 'transactions.location_id', $location_id, $permitted_locations);
            $row = $query->select(DB::raw('COUNT(*) as count'), DB::raw("SUM(transactions.final_total - $paid) as amount"))->first();
            $items[] = [
                'key' => 'purchase_dues', 'icon' => 'cart', 'tone' => 'warning',
                'count' => (int) $row->count, 'amount' => (float) $row->amount,
                'url' => action([\App\Http\Controllers\PurchaseController::class, 'index']),
            ];
        }

        $chequeTypes = array_filter([
            $user->can('sell.payments') ? Cheque::TYPE_RECEIVABLE : null,
            $user->can('purchase.payments') ? Cheque::TYPE_PAYABLE : null,
        ]);
        if (! empty($chequeTypes) && Schema::hasTable('cheques')) {
            $query = Cheque::where('business_id', $business_id)
                ->whereIn('type', $chequeTypes)
                ->where('status', Cheque::STATUS_PENDING)
                ->whereDate('due_date', '<=', Carbon::today()->addDays(7)->toDateString());
            $this->filterLocation($query, 'location_id', $location_id, $permitted_locations, true);
            $row = $query->select(
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(IF(type = 'receivable', amount, 0)) as receivable"),
                DB::raw("SUM(IF(type = 'payable', amount, 0)) as payable")
            )->first();
            $items[] = [
                'key' => 'cheques_due', 'icon' => 'cheque', 'tone' => 'info',
                'count' => (int) $row->count, 'amount' => (float) $row->receivable + (float) $row->payable,
                'url' => action([\App\Http\Controllers\ChequeController::class, 'index']),
            ];
        }

        if ($user->can('stock_report.view')) {
            $count = $this->productUtil->getProductAlert($business_id, $permitted_locations)->get()->count();
            $items[] = [
                'key' => 'low_stock', 'icon' => 'box', 'tone' => 'warning',
                'count' => $count, 'amount' => null,
                'url' => action([\App\Http\Controllers\ReportController::class, 'getStockReport']),
            ];
        }

        if (! empty($common_settings['enable_sales_order']) && ($user->can('so.view_all') || $user->can('so.view_own'))) {
            $query = Transaction::where('business_id', $business_id)
                ->where('type', 'sales_order')
                ->whereIn('status', ['ordered', 'partial']);
            if (! $user->can('so.view_all')) {
                $query->where('created_by', $user->id);
            }
            $this->filterLocation($query, 'location_id', $location_id, $permitted_locations);
            $items[] = [
                'key' => 'pending_sales_orders', 'icon' => 'clipboard', 'tone' => 'brand',
                'count' => $query->count(), 'amount' => null,
                'url' => action([\App\Http\Controllers\SalesOrderController::class, 'index']),
            ];
        }

        if ($user->can('access_shipping') || $user->can('access_own_shipping') || $user->can('access_pending_shipments_only')) {
            $query = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->whereIn('shipping_status', ['ordered', 'packed', 'shipped']);
            $this->filterLocation($query, 'location_id', $location_id, $permitted_locations);
            $items[] = [
                'key' => 'pending_shipments', 'icon' => 'truck', 'tone' => 'info',
                'count' => $query->count(), 'amount' => null,
                'url' => action([\App\Http\Controllers\SellController::class, 'shipments']),
            ];
        }

        return $items;
    }

    /**
     * Unpaid customer invoices by how long they are past their due date
     * (invoice date + pay term; invoices without a pay term are due on their date).
     */
    public function receivablesAging($business_id, $location_id, $permitted_locations)
    {
        $today = Carbon::today()->toDateString();
        $dueDate = "DATE(DATE_ADD(transactions.transaction_date, INTERVAL IF(transactions.pay_term_number IS NULL, 0, IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number)) DAY))";
        $paid = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id)';

        $invoices = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->where('transactions.payment_status', '!=', 'paid')
            ->select(
                DB::raw("DATEDIFF('$today', $dueDate) as days_late"),
                DB::raw("transactions.final_total - $paid as due")
            );
        $this->filterLocation($invoices, 'transactions.location_id', $location_id, $permitted_locations);

        $buckets = ['not_due' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $rows = DB::query()->fromSub($invoices, 'inv')
            ->where('due', '>', 0)
            ->select(
                DB::raw("CASE WHEN days_late <= 0 THEN 'not_due' WHEN days_late <= 30 THEN '1_30' WHEN days_late <= 60 THEN '31_60' WHEN days_late <= 90 THEN '61_90' ELSE '90_plus' END as bucket"),
                DB::raw('SUM(due) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('bucket')
            ->get();

        $counts = $buckets;
        foreach ($rows as $row) {
            $buckets[$row->bucket] = (float) $row->total;
            $counts[$row->bucket] = (int) $row->count;
        }

        return ['totals' => $buckets, 'counts' => $counts, 'total' => array_sum($buckets)];
    }

    /**
     * Sales value by weekday × hour over the 12 weeks that end on $end.
     * Weekdays are ordered Saturday → Friday (MySQL DAYOFWEEK: 1 = Sunday).
     */
    public function salesHeatmap($business_id, $end, $location_id, $permitted_locations)
    {
        $to = Carbon::parse($end);
        $from = $to->copy()->subWeeks(12)->addDay();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->select(
                DB::raw('DAYOFWEEK(transaction_date) as dow'),
                DB::raw('HOUR(transaction_date) as hr'),
                DB::raw('SUM(final_total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('dow', 'hr');
        $this->filterLocation($query, 'location_id', $location_id, $permitted_locations);

        $cells = [];
        foreach ($query->get() as $row) {
            $cells[(int) $row->dow][(int) $row->hr] = ['total' => (float) $row->total, 'count' => (int) $row->count];
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => [7, 1, 2, 3, 4, 5, 6], 'cells' => $cells];
    }

    /**
     * Stocked products expected to run out soon at the sales rate of the last 30 days.
     */
    public function stockForecast($business_id, $location_id, $permitted_locations, $horizon_days = 21, $limit = 6)
    {
        $since = Carbon::today()->subDays(29)->toDateString();

        $sold = TransactionSellLine::join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereDate('t.transaction_date', '>=', $since)
            ->select('transaction_sell_lines.variation_id', DB::raw('SUM(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as sold'))
            ->groupBy('transaction_sell_lines.variation_id');
        $this->filterLocation($sold, 't.location_id', $location_id, $permitted_locations);

        $stock = DB::table('variation_location_details as vld')
            ->select('vld.variation_id', DB::raw('SUM(vld.qty_available) as stock'))
            ->groupBy('vld.variation_id');
        $this->filterLocation($stock, 'vld.location_id', $location_id, $permitted_locations);

        $rows = DB::table('variations as v')
            ->joinSub($sold, 's', 's.variation_id', '=', 'v.id')
            ->joinSub($stock, 'st', 'st.variation_id', '=', 'v.id')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereNull('v.deleted_at')
            ->where('s.sold', '>', 0)
            ->select('p.name', 'p.type', 'pv.name as product_variation', 'v.name as variation', 'u.short_name as unit',
                'st.stock', 's.sold', DB::raw('GREATEST(st.stock, 0) / (s.sold / 30) as days_left'))
            ->having('days_left', '<=', $horizon_days)
            ->orderBy('days_left')
            ->orderByDesc('s.sold')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $row->label = $row->type == 'variable' ? $row->name.' - '.$row->product_variation.' - '.$row->variation : $row->name;
            $row->days_left = (int) floor($row->days_left);
            $row->daily = round($row->sold / 30, 1);

            return $row;
        });
    }

    /**
     * Widgets from enabled modules (DataController::home_dashboard_widgets).
     * Each module returns a list of:
     * ['title', 'icon', 'tone', 'url', 'items' => [['label', 'value', 'money' => bool, 'tone' => null|success|warning|danger|info]]]
     */
    public function moduleWidgets(ModuleUtil $moduleUtil, $business_id, $location_id)
    {
        $widgets = [];
        foreach ($moduleUtil->getModuleData('home_dashboard_widgets', ['business_id' => $business_id, 'location_id' => $location_id]) as $module => $list) {
            foreach ((array) $list as $widget) {
                if (! empty($widget['title']) && ! empty($widget['items'])) {
                    $widgets[] = $widget + ['module' => $module, 'icon' => 'bars', 'tone' => 'brand', 'url' => null];
                }
            }
        }

        return $widgets;
    }

    /**
     * The user's saved layout merged with the defaults: every zone lists all of
     * its sections (saved order first, new sections appended) plus the hidden keys.
     */
    public function userLayout($user)
    {
        $saved = json_decode((string) $user->dashboard_layout, true) ?: [];
        $order = [];
        foreach (self::SECTIONS as $zone => $defaults) {
            $keys = array_values(array_intersect((array) ($saved['order'][$zone] ?? []), $defaults));
            $order[$zone] = array_values(array_unique(array_merge($keys, $defaults)));
        }
        $hidden = array_values(array_intersect((array) ($saved['hidden'] ?? []), array_merge(...array_values(self::SECTIONS))));

        return ['order' => $order, 'hidden' => $hidden];
    }

    /**
     * Saves the order per zone and the visible sections; null resets to the default layout.
     */
    public function saveUserLayout($user, ?array $order, array $visible = [])
    {
        if ($order === null) {
            $user->dashboard_layout = null;
        } else {
            $layout = ['order' => [], 'hidden' => []];
            foreach (self::SECTIONS as $zone => $defaults) {
                $layout['order'][$zone] = array_values(array_intersect((array) ($order[$zone] ?? []), $defaults));
                // بس الأقسام اللي كانت في النافذة؛ قسم مش متاح لليوزر دلوقتي مايتسجلش مخفي
                $layout['hidden'] = array_merge($layout['hidden'], array_values(array_diff($layout['order'][$zone], $visible)));
            }
            $user->dashboard_layout = json_encode($layout);
        }
        $user->save();
    }

    /**
     * Sections to render per zone: visible and available to this user, in the
     * user's order. The cards zone comes back as columns: one card per column,
     * and the cards beyond CARD_COLUMNS stacked in the last column.
     */
    public function renderLayout(array $layout, array $available)
    {
        $out = [];
        foreach ($layout['order'] as $zone => $keys) {
            $out[$zone] = array_values(array_filter($keys, function ($key) use ($layout, $available) {
                return ! empty($available[$key]) && ! in_array($key, $layout['hidden']);
            }));
        }

        $columns = array_map(fn ($key) => [$key], array_slice($out['cards'], 0, self::CARD_COLUMNS - 1));
        $rest = array_slice($out['cards'], self::CARD_COLUMNS - 1);
        if (! empty($rest)) {
            $columns[] = $rest;
        }
        $out['cards'] = $columns;

        return $out;
    }

    protected function bucketFormat($bucket)
    {
        return ['hour' => '%H', 'day' => '%Y-%m-%d', 'month' => '%Y-%m'][$bucket];
    }

    protected function permitted($permitted_locations)
    {
        return $permitted_locations == 'all' ? null : $permitted_locations;
    }

    protected function filterLocation($query, $column, $location_id, $permitted_locations, $nullable = false)
    {
        if ($permitted_locations != 'all') {
            $query->where(function ($q) use ($column, $permitted_locations, $nullable) {
                $q->whereIn($column, (array) $permitted_locations);
                if ($nullable) {
                    $q->orWhereNull($column);
                }
            });
        }

        if (! empty($location_id)) {
            $query->where($column, $location_id);
        }
    }
}
