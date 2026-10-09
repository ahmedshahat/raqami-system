<?php

namespace App\Http\Controllers;

use App\Account;
use App\BusinessLocation;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class DashboardV2Controller extends Controller
{
    protected $businessUtil;

    protected $transactionUtil;

    public function __construct(BusinessUtil $businessUtil, TransactionUtil $transactionUtil)
    {
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user->user_type === 'user_customer') {
            return redirect()->action([\Modules\Crm\Http\Controllers\DashboardController::class, 'index']);
        }

        $this->ensureManager($user);
        $businessId = request()->session()->get('user.business_id');
        $allLocations = BusinessLocation::forDropdown($businessId)->toArray();

        return view('dashboard_v2.index', compact('allLocations'));
    }

    public function summary(Request $request)
    {
        $this->ensureManager(auth()->user());

        $validated = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $businessId = (int) $request->session()->get('user.business_id');
        $start = Carbon::createFromFormat('Y-m-d', $validated['start'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $validated['end'])->endOfDay();
        $locationId = ! empty($validated['location_id']) ? (int) $validated['location_id'] : null;

        if ($locationId && ! BusinessLocation::where('business_id', $businessId)->where('id', $locationId)->exists()) {
            abort(422, 'Invalid business location.');
        }

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');
        $periodDays = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodDays - 1);

        $profitLoss = $this->transactionUtil->getProfitLossDetails($businessId, $locationId, $startDate, $endDate, null, 'all');
        $sales = $this->transactionUtil->getSellTotals($businessId, $startDate, $endDate, $locationId);
        $purchases = $this->transactionUtil->getPurchaseTotals($businessId, $startDate, $endDate, $locationId);
        $transactionTotals = $this->transactionUtil->getTransactionTotals(
            $businessId,
            ['purchase_return', 'sell_return', 'expense'],
            $startDate,
            $endDate,
            $locationId
        );

        $previousSales = $this->transactionUtil->getSellTotals(
            $businessId,
            $previousStart->format('Y-m-d'),
            $previousEnd->format('Y-m-d'),
            $locationId
        );
        $previousTransactions = $this->transactionUtil->getTransactionTotals(
            $businessId,
            ['expense'],
            $previousStart->format('Y-m-d'),
            $previousEnd->format('Y-m-d'),
            $locationId
        );

        $portfolioReceivables = $this->outstandingTotal($businessId, 'sell', $locationId);
        $portfolioPayables = $this->outstandingTotal($businessId, 'purchase', $locationId);
        $cash = $this->cashPosition($businessId);
        $stock = $this->stockPosition($businessId, $locationId, $endDate);
        $tax = $this->taxPosition($businessId, $startDate, $endDate, $locationId);
        $ageing = $this->receivablesAgeing($businessId, $locationId);

        $totalSales = $this->number($sales['total_sell_inc_tax'] ?? 0);
        $periodReceivables = $this->number($sales['invoice_due'] ?? 0);
        $totalPurchases = $this->number($purchases['total_purchase_inc_tax'] ?? 0);
        $expenses = $this->number($transactionTotals['total_expense'] ?? 0);
        $netProfit = $this->number($profitLoss['net_profit'] ?? 0);
        $grossProfit = $this->number($profitLoss['gross_profit'] ?? 0);
        $previousSalesValue = $this->number($previousSales['total_sell_inc_tax'] ?? 0);
        $previousExpenses = $this->number($previousTransactions['total_expense'] ?? 0);

        return response()->json([
            'meta' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'timezone' => date_default_timezone_get(),
            ],
            'period' => ['start' => $startDate, 'end' => $endDate, 'days' => $periodDays],
            'kpis' => [
                'sales' => $totalSales,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'purchases' => $totalPurchases,
                'expenses' => $expenses,
                'receivables' => $portfolioReceivables,
                'payables' => $portfolioPayables,
                'cash_balance' => $cash['total'],
                'stock_cost' => $stock['cost'],
                'stock_sale_value' => $stock['sale_value'],
                'stock_potential_profit' => $stock['potential_profit'],
                'tax_due' => $tax['net_due'],
                'net_position' => $cash['total'] + $portfolioReceivables + $stock['cost'] - $portfolioPayables - max(0, $tax['net_due']),
                'collection_rate' => $totalSales > 0 ? max(0, min(100, (($totalSales - $periodReceivables) / $totalSales) * 100)) : 0,
                'net_margin' => $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0,
            ],
            'comparisons' => [
                'sales' => $this->percentageChange($totalSales, $previousSalesValue),
                'expenses' => $this->percentageChange($expenses, $previousExpenses),
            ],
            'cash' => $cash,
            'stock' => $stock,
            'tax' => $tax,
            'ageing' => $ageing,
            'trends' => $this->transactionTrends($businessId, $start, $end, $locationId),
            'locations' => $this->locationPerformance($businessId, $startDate, $endDate, $locationId),
            'top_debtors' => $this->topOutstandingContacts($businessId, 'sell', $locationId),
            'top_creditors' => $this->topOutstandingContacts($businessId, 'purchase', $locationId),
            'top_products' => $this->topProducts($businessId, $startDate, $endDate, $locationId),
            'alerts' => $this->managerAlerts(
                $portfolioPayables,
                $cash['total'],
                $stock,
                $tax,
                $ageing,
                $expenses,
                $totalSales
            ),
            'details' => [
                'sales_due_in_period' => $periodReceivables,
                'purchase_due_in_period' => $this->number($purchases['purchase_due'] ?? 0),
                'sales_returns' => $this->number($transactionTotals['total_sell_return_inc_tax'] ?? 0),
                'purchase_returns' => $this->number($transactionTotals['total_purchase_return_inc_tax'] ?? 0),
            ],
        ]);
    }

    private function ensureManager($user)
    {
        if (! $this->businessUtil->is_admin($user)) {
            abort(403, 'Manager dashboard access only.');
        }
    }

    private function number($value)
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function percentageChange($current, $previous)
    {
        if (abs($previous) < 0.00001) {
            return $current == 0 ? 0 : 100;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }

    private function outstandingExpression()
    {
        return "transactions.final_total - (SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments AS tp WHERE tp.transaction_id = transactions.id)";
    }

    private function outstandingQuery($businessId, $type, $locationId = null)
    {
        $query = Transaction::where('transactions.business_id', $businessId)
            ->where('transactions.type', $type)
            ->whereIn('transactions.payment_status', ['due', 'partial']);

        if ($type === 'sell') {
            $query->where('transactions.status', 'final');
        }
        if ($locationId) {
            $query->where('transactions.location_id', $locationId);
        }

        return $query;
    }

    private function outstandingTotal($businessId, $type, $locationId = null)
    {
        $expression = $this->outstandingExpression();
        $row = $this->outstandingQuery($businessId, $type, $locationId)
            ->selectRaw("COALESCE(SUM(GREATEST(($expression), 0)), 0) AS outstanding")
            ->first();

        return $this->number($row->outstanding ?? 0);
    }

    private function topOutstandingContacts($businessId, $type, $locationId = null)
    {
        $expression = $this->outstandingExpression();

        return $this->outstandingQuery($businessId, $type, $locationId)
            ->join('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->select(
                'contacts.id',
                DB::raw("COALESCE(NULLIF(contacts.supplier_business_name, ''), contacts.name) AS name"),
                DB::raw("SUM(GREATEST(($expression), 0)) AS amount")
            )
            ->groupBy('contacts.id', 'contacts.name', 'contacts.supplier_business_name')
            ->having('amount', '>', 0)
            ->orderByDesc('amount')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                return ['id' => $row->id, 'name' => $row->name, 'amount' => $this->number($row->amount)];
            })->values();
    }

    private function receivablesAgeing($businessId, $locationId = null)
    {
        $expression = $this->outstandingExpression();
        $rows = $this->outstandingQuery($businessId, 'sell', $locationId)
            ->select(
                'transactions.transaction_date',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                DB::raw("GREATEST(($expression), 0) AS outstanding")
            )->get();

        $buckets = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'days_90_plus' => 0];
        $today = Carbon::today();

        foreach ($rows as $row) {
            $amount = $this->number($row->outstanding);
            $dueDate = Carbon::parse($row->transaction_date);
            if ($row->pay_term_number && $row->pay_term_type === 'months') {
                $dueDate->addMonths((int) $row->pay_term_number);
            } elseif ($row->pay_term_number) {
                $dueDate->addDays((int) $row->pay_term_number);
            }

            if ($dueDate->gte($today)) {
                $buckets['current'] += $amount;
                continue;
            }

            $lateDays = $dueDate->diffInDays($today);
            if ($lateDays <= 30) {
                $buckets['days_1_30'] += $amount;
            } elseif ($lateDays <= 60) {
                $buckets['days_31_60'] += $amount;
            } elseif ($lateDays <= 90) {
                $buckets['days_61_90'] += $amount;
            } else {
                $buckets['days_90_plus'] += $amount;
            }
        }

        $buckets['overdue_total'] = $buckets['days_1_30'] + $buckets['days_31_60'] + $buckets['days_61_90'] + $buckets['days_90_plus'];

        return $buckets;
    }

    private function cashPosition($businessId)
    {
        $accounts = Account::where('accounts.business_id', $businessId)
            ->where('accounts.is_closed', 0)
            ->leftJoin('account_transactions AS account_entries', function ($join) {
                $join->on('account_entries.account_id', '=', 'accounts.id')->whereNull('account_entries.deleted_at');
            })
            ->select(
                'accounts.id',
                'accounts.name',
                DB::raw("COALESCE(SUM(IF(account_entries.type = 'credit', account_entries.amount, -1 * account_entries.amount)), 0) AS balance")
            )
            ->groupBy('accounts.id', 'accounts.name')
            ->orderByDesc('balance')
            ->get();

        return [
            'total' => $accounts->sum(function ($account) { return $this->number($account->balance); }),
            'accounts' => $accounts->map(function ($account) {
                return ['id' => $account->id, 'name' => $account->name, 'balance' => $this->number($account->balance)];
            })->values(),
        ];
    }

    private function stockPosition($businessId, $locationId, $endDate)
    {
        $cost = $this->number($this->transactionUtil->getOpeningClosingStock($businessId, $endDate, $locationId, false, false, [], 'all'));
        $saleValue = $this->number($this->transactionUtil->getOpeningClosingStock($businessId, $endDate, $locationId, false, true, [], 'all'));

        $base = DB::table('variation_location_details AS stock')
            ->join('products AS products', 'products.id', '=', 'stock.product_id')
            ->join('variations AS variations', 'variations.id', '=', 'stock.variation_id')
            ->where('products.business_id', $businessId)
            ->where('products.enable_stock', 1)
            ->where('products.is_inactive', 0)
            ->whereNull('variations.deleted_at');
        if ($locationId) {
            $base->where('stock.location_id', $locationId);
        }

        $totals = (clone $base)->selectRaw(
            'COUNT(DISTINCT products.id) AS products_count, '
            .'COALESCE(SUM(stock.qty_available), 0) AS units_count, '
            .'SUM(CASE WHEN stock.qty_available <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count, '
            .'SUM(CASE WHEN products.alert_quantity IS NOT NULL AND stock.qty_available > 0 AND stock.qty_available <= products.alert_quantity THEN 1 ELSE 0 END) AS low_stock_count, '
            .'SUM(CASE WHEN stock.qty_available < 0 THEN 1 ELSE 0 END) AS negative_stock_count'
        )->first();

        return [
            'cost' => $cost,
            'sale_value' => $saleValue,
            'potential_profit' => $saleValue - $cost,
            'products_count' => (int) ($totals->products_count ?? 0),
            'units_count' => $this->number($totals->units_count ?? 0),
            'out_of_stock_count' => (int) ($totals->out_of_stock_count ?? 0),
            'low_stock_count' => (int) ($totals->low_stock_count ?? 0),
            'negative_stock_count' => (int) ($totals->negative_stock_count ?? 0),
        ];
    }

    private function taxPosition($businessId, $startDate, $endDate, $locationId)
    {
        $input = $this->transactionUtil->getInputTax($businessId, $startDate, $endDate, $locationId);
        $output = $this->transactionUtil->getOutputTax($businessId, $startDate, $endDate, $locationId);
        $expense = $this->transactionUtil->getExpenseTax($businessId, $startDate, $endDate, $locationId);
        $inputTax = $this->number($input['total_tax'] ?? 0) + $this->number($expense['total_tax'] ?? 0);
        $outputTax = $this->number($output['total_tax'] ?? 0);

        return ['input' => $inputTax, 'output' => $outputTax, 'net_due' => $outputTax - $inputTax];
    }

    private function transactionTrends($businessId, Carbon $start, Carbon $end, $locationId)
    {
        $daily = $start->diffInDays($end) <= 45;
        $dateExpression = $daily ? "DATE_FORMAT(transaction_date, '%Y-%m-%d')" : "DATE_FORMAT(transaction_date, '%Y-%m')";
        $query = Transaction::where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereIn('type', ['sell', 'purchase', 'expense'])
            ->where(function ($query) {
                $query->where('type', '!=', 'sell')->orWhere('status', 'final');
            });
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $rows = $query->select(
            DB::raw("$dateExpression AS period_key"),
            DB::raw("SUM(IF(type = 'sell', final_total, 0)) AS sales"),
            DB::raw("SUM(IF(type = 'purchase', final_total, 0)) AS purchases"),
            DB::raw("SUM(IF(type = 'expense', final_total, 0)) AS expenses")
        )->groupBy('period_key')->orderBy('period_key')->get()->keyBy('period_key');

        $cursor = $daily ? $start->copy() : $start->copy()->startOfMonth();
        $trendEnd = $daily ? $end->copy() : $end->copy()->startOfMonth();
        $points = [];
        while ($cursor->lte($trendEnd)) {
            $key = $cursor->format($daily ? 'Y-m-d' : 'Y-m');
            $row = $rows->get($key);
            $points[] = [
                'key' => $key,
                'label' => $cursor->format($daily ? 'd/m' : 'm/Y'),
                'sales' => $this->number($row->sales ?? 0),
                'purchases' => $this->number($row->purchases ?? 0),
                'expenses' => $this->number($row->expenses ?? 0),
            ];
            if ($daily) {
                $cursor->addDay();
            } else {
                $cursor->addMonth();
            }
        }

        return $points;
    }

    private function locationPerformance($businessId, $startDate, $endDate, $locationId)
    {
        $query = Transaction::join('business_locations AS locations', 'locations.id', '=', 'transactions.location_id')
            ->where('transactions.business_id', $businessId)
            ->whereIn('transactions.type', ['sell', 'purchase', 'expense'])
            ->where(function ($query) {
                $query->where('transactions.type', '!=', 'sell')
                    ->orWhere('transactions.status', 'final');
            })
            ->whereDate('transactions.transaction_date', '>=', $startDate)
            ->whereDate('transactions.transaction_date', '<=', $endDate);
        if ($locationId) {
            $query->where('transactions.location_id', $locationId);
        }

        return $query->select(
            'locations.id',
            'locations.name',
            DB::raw("SUM(IF(transactions.type = 'sell', transactions.final_total, 0)) AS sales"),
            DB::raw("SUM(IF(transactions.type = 'purchase', transactions.final_total, 0)) AS purchases"),
            DB::raw("SUM(IF(transactions.type = 'expense', transactions.final_total, 0)) AS expenses")
        )
            ->groupBy('locations.id', 'locations.name')
            ->orderByDesc('sales')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $sales = $this->number($row->sales);
                $purchases = $this->number($row->purchases);
                $expenses = $this->number($row->expenses);

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'sales' => $sales,
                    'purchases' => $purchases,
                    'expenses' => $expenses,
                    'operating_net' => $sales - $purchases - $expenses,
                ];
            })->values();
    }

    private function topProducts($businessId, $startDate, $endDate, $locationId)
    {
        $query = DB::table('transaction_sell_lines AS lines')
            ->join('transactions', 'transactions.id', '=', 'lines.transaction_id')
            ->join('products', 'products.id', '=', 'lines.product_id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereDate('transactions.transaction_date', '>=', $startDate)
            ->whereDate('transactions.transaction_date', '<=', $endDate);
        if ($locationId) {
            $query->where('transactions.location_id', $locationId);
        }

        return $query->select(
            'products.id',
            'products.name',
            DB::raw('SUM(lines.quantity - lines.quantity_returned) AS quantity'),
            DB::raw('SUM((lines.quantity - lines.quantity_returned) * lines.unit_price_inc_tax) AS amount')
        )->groupBy('products.id', 'products.name')
            ->orderByDesc('amount')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                return ['id' => $row->id, 'name' => $row->name, 'quantity' => $this->number($row->quantity), 'amount' => $this->number($row->amount)];
            })->values();
    }

    private function managerAlerts($payables, $cash, array $stock, array $tax, array $ageing, $expenses, $sales)
    {
        $alerts = [];
        if ($ageing['days_90_plus'] > 0) {
            $alerts[] = ['severity' => 'danger', 'type' => 'receivables', 'amount' => $ageing['days_90_plus'], 'count' => null];
        }
        if ($payables > max($cash, 0)) {
            $alerts[] = ['severity' => 'danger', 'type' => 'liquidity_gap', 'amount' => $payables - max($cash, 0), 'count' => null];
        }
        if ($stock['out_of_stock_count'] > 0) {
            $alerts[] = ['severity' => 'warning', 'type' => 'out_of_stock', 'amount' => null, 'count' => $stock['out_of_stock_count']];
        }
        if ($stock['low_stock_count'] > 0) {
            $alerts[] = ['severity' => 'warning', 'type' => 'low_stock', 'amount' => null, 'count' => $stock['low_stock_count']];
        }
        if ($stock['negative_stock_count'] > 0) {
            $alerts[] = ['severity' => 'danger', 'type' => 'negative_stock', 'amount' => null, 'count' => $stock['negative_stock_count']];
        }
        if ($tax['net_due'] > 0) {
            $alerts[] = ['severity' => 'info', 'type' => 'tax_due', 'amount' => $tax['net_due'], 'count' => null];
        }
        if ($sales > 0 && ($expenses / $sales) >= 0.25) {
            $alerts[] = ['severity' => 'warning', 'type' => 'high_expenses', 'amount' => $expenses, 'count' => null];
        }
        if (empty($alerts)) {
            $alerts[] = ['severity' => 'success', 'type' => 'all_good', 'amount' => null, 'count' => null];
        }

        return array_slice($alerts, 0, 7);
    }
}
