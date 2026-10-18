<?php

namespace App\Utils;

use App\Cheque;
use App\Events\TransactionPaymentAdded;
use App\Transaction;
use App\TransactionPayment;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class ChequeUtil extends Util
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Base query for cheques of a business, limited to the user's permitted locations.
     * Cheques paid on account (no invoice) have no location and are always visible.
     */
    public function query($business_id, $type = null)
    {
        $query = Cheque::where('cheques.business_id', $business_id);

        if (! empty($type)) {
            $query->where('cheques.type', $type);
        }

        $permitted_locations = auth()->user()->permitted_locations($business_id);
        if ($permitted_locations != 'all') {
            $query->where(function ($q) use ($permitted_locations) {
                $q->whereNull('cheques.location_id')
                    ->orWhereIn('cheques.location_id', $permitted_locations);
            });
        }

        return $query;
    }

    /**
     * Pending amounts grouped by due bucket.
     */
    public function summary($business_id, $type, $location_id = null)
    {
        $today = Carbon::today()->format('Y-m-d');
        $in_7 = Carbon::today()->addDays(7)->format('Y-m-d');
        $in_30 = Carbon::today()->addDays(30)->format('Y-m-d');

        $query = $this->query($business_id, $type)->where('status', Cheque::STATUS_PENDING);
        if (! empty($location_id)) {
            $query->where('cheques.location_id', $location_id);
        }

        $row = $query->select(
            DB::raw("SUM(IF(due_date < '$today', amount, 0)) as overdue"),
            DB::raw("SUM(IF(due_date = '$today', amount, 0)) as today"),
            DB::raw("SUM(IF(due_date > '$today' AND due_date <= '$in_7', amount, 0)) as this_week"),
            DB::raw("SUM(IF(due_date > '$in_7' AND due_date <= '$in_30', amount, 0)) as this_month"),
            DB::raw("SUM(IF(due_date > '$in_30', amount, 0)) as later"),
            DB::raw('SUM(amount) as total'),
            DB::raw("SUM(IF(due_date < '$today', 1, 0)) as overdue_count"),
            DB::raw('COUNT(*) as total_count')
        )->first();

        $summary = [];
        foreach (['overdue', 'today', 'this_week', 'this_month', 'later', 'total', 'overdue_count', 'total_count'] as $key) {
            $summary[$key] = (float) ($row->{$key} ?? 0);
        }

        return $summary;
    }

    /**
     * Weekly cash flow of pending cheques: incoming (receivable) vs outgoing (payable).
     * Weeks are 7-day windows starting today; overdue and "later" are separate rows.
     */
    public function cashFlow($business_id, $weeks = 12, $include_receivable = true, $include_payable = true)
    {
        $types = [];
        if ($include_receivable) {
            $types[] = Cheque::TYPE_RECEIVABLE;
        }
        if ($include_payable) {
            $types[] = Cheque::TYPE_PAYABLE;
        }

        $cheques = $this->query($business_id)
            ->whereIn('type', $types)
            ->where('status', Cheque::STATUS_PENDING)
            ->select('type', 'due_date', 'amount')
            ->get();

        $today = Carbon::today();
        $rows = [];
        $rows['overdue'] = ['key' => 'overdue', 'from' => null, 'to' => $today->copy()->subDay(), 'in' => 0, 'out' => 0];
        for ($i = 0; $i < $weeks; $i++) {
            $from = $today->copy()->addDays($i * 7);
            $rows['w'.$i] = ['key' => 'week', 'from' => $from, 'to' => $from->copy()->addDays(6), 'in' => 0, 'out' => 0];
        }
        $later_from = $today->copy()->addDays($weeks * 7);
        $rows['later'] = ['key' => 'later', 'from' => $later_from, 'to' => null, 'in' => 0, 'out' => 0];

        foreach ($cheques as $cheque) {
            $due = Carbon::parse($cheque->due_date)->startOfDay();
            if ($due->lt($today)) {
                $bucket = 'overdue';
            } elseif ($due->gte($later_from)) {
                $bucket = 'later';
            } else {
                $bucket = 'w'.intdiv($today->diffInDays($due), 7);
            }
            $field = $cheque->type == Cheque::TYPE_RECEIVABLE ? 'in' : 'out';
            $rows[$bucket][$field] += $cheque->amount;
        }

        $cumulative = 0;
        foreach ($rows as $k => $row) {
            $rows[$k]['net'] = $row['in'] - $row['out'];
            $cumulative += $rows[$k]['net'];
            $rows[$k]['cumulative'] = $cumulative;
        }

        return array_values($rows);
    }

    /**
     * Collect a receivable cheque / clear a payable cheque.
     * Creates the payment on the cheque's invoice (up to its due amount);
     * anything left is paid on the contact account (other due invoices, then advance).
     *
     * @param  string  $collected_on  Y-m-d H:i:s
     */
    public function collect($business_id, $cheque_id, $collected_on, $account_id = null, $note = null)
    {
        $cheque = Cheque::where('business_id', $business_id)->lockForUpdate()->findOrFail($cheque_id);
        if (! $cheque->isPending()) {
            throw new \Exception(__('cheque.only_pending'));
        }

        $account_id = ! empty($account_id) ? $account_id : $cheque->account_id;
        $remaining = (float) $cheque->amount;
        $payment_ids = [];
        $transaction_type = $cheque->type == Cheque::TYPE_RECEIVABLE ? 'sell' : 'purchase';
        $prefix_type = $cheque->type == Cheque::TYPE_RECEIVABLE ? 'sell_payment' : 'purchase_payment';
        $payment_note = trim(__('lang_v1.cheque_no').' '.$cheque->cheque_number.' '.($cheque->bank_name ?? '').' '.($note ?? ''));

        $transaction = null;
        if (! empty($cheque->transaction_id)) {
            $transaction = Transaction::where('business_id', $business_id)->find($cheque->transaction_id);
        }

        if (! empty($transaction) && $transaction->status != 'draft') {
            $due = $transaction->final_total - $this->transactionUtil->getTotalPaid($transaction->id);
            $pay = round(min($remaining, max($due, 0)), 4);

            if ($pay > 0) {
                $ref_count = $this->transactionUtil->setAndGetReferenceCount($prefix_type, $business_id);
                $payment_data = [
                    'transaction_id' => $transaction->id,
                    'business_id' => $business_id,
                    'amount' => $pay,
                    'method' => 'cheque',
                    'cheque_number' => $cheque->cheque_number,
                    'paid_on' => $collected_on,
                    'created_by' => auth()->user()->id,
                    'payment_for' => $transaction->contact_id,
                    'payment_ref_no' => $this->transactionUtil->generateReferenceNumber($prefix_type, $ref_count, $business_id),
                    'account_id' => $account_id,
                    'note' => $payment_note,
                ];
                $tp = TransactionPayment::create($payment_data);
                $payment_data['transaction_type'] = $transaction->type;
                event(new TransactionPaymentAdded($tp, $payment_data));

                $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

                $payment_ids[] = $tp->id;
                $remaining = round($remaining - $pay, 4);
            }
        }

        if ($remaining > 0) {
            $request = new Request([
                'contact_id' => $cheque->contact_id,
                'amount' => $remaining,
                'method' => 'cheque',
                'cheque_number' => $cheque->cheque_number,
                'note' => $payment_note,
                'paid_on' => $collected_on,
                'account_id' => $account_id,
                'due_payment_type' => $transaction_type,
            ]);
            $parent_payment = $this->transactionUtil->payContact($request, false);
            $payment_ids[] = $parent_payment->id;
        }

        $cheque->status = Cheque::STATUS_COLLECTED;
        $cheque->payment_id = $payment_ids[0] ?? null;
        $cheque->account_id = $account_id;
        $cheque->status_changed_on = $collected_on;
        $cheque->status_note = $note;
        $cheque->updated_by = auth()->user()->id;
        $cheque->save();

        return $cheque;
    }

    /**
     * Bounced / cancelled cheques never created a payment, so the invoice simply stays due.
     */
    public function changeStatus($business_id, $cheque_id, $status, $date = null, $note = null)
    {
        $cheque = Cheque::where('business_id', $business_id)->lockForUpdate()->findOrFail($cheque_id);
        if (! $cheque->isPending()) {
            throw new \Exception(__('cheque.only_pending'));
        }

        $cheque->status = $status;
        $cheque->status_changed_on = ! empty($date) ? $date : Carbon::now()->toDateTimeString();
        $cheque->status_note = $note;
        $cheque->updated_by = auth()->user()->id;
        $cheque->save();

        return $cheque;
    }

    /**
     * Pending cheques per contact (used on contact pages).
     */
    public function pendingForContact($business_id, $contact_id)
    {
        return (float) Cheque::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->where('status', Cheque::STATUS_PENDING)
            ->sum('amount');
    }
}
