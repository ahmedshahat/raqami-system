<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Cheques register.
 *
 * A cheque taken from a customer (receivable) or given to a supplier (payable)
 * is kept here and is NOT counted as a payment until it is collected / cleared.
 * On collection a normal transaction payment is created (see ChequeUtil::collect).
 */
class Cheque extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'received_on' => 'datetime',
        'status_changed_on' => 'datetime',
    ];

    public const TYPE_RECEIVABLE = 'receivable';

    public const TYPE_PAYABLE = 'payable';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses()
    {
        return [
            self::STATUS_PENDING => __('cheque.status_pending'),
            self::STATUS_COLLECTED => __('cheque.status_collected'),
            self::STATUS_BOUNCED => __('cheque.status_bounced'),
            self::STATUS_CANCELLED => __('cheque.status_cancelled'),
        ];
    }

    /**
     * Payment lines with method "cheque" on these transaction types go to the register.
     */
    public static function typeForTransaction($transaction_type)
    {
        if ($transaction_type == 'sell') {
            return self::TYPE_RECEIVABLE;
        }
        if ($transaction_type == 'purchase') {
            return self::TYPE_PAYABLE;
        }

        return null;
    }

    /**
     * Register a cheque from a payment line / payment form input.
     *
     * @param  array  $data  amount (numeric), cheque_number, cheque_bank_name, cheque_due_date (Y-m-d), paid_on (Y-m-d H:i:s), account_id, note
     */
    public static function register($business_id, $type, $contact_id, $transaction, array $data, $user_id = null)
    {
        $received_on = ! empty($data['paid_on']) ? $data['paid_on'] : \Carbon::now()->toDateTimeString();
        $due_date = ! empty($data['cheque_due_date']) ? \Carbon::parse($data['cheque_due_date'])->format('Y-m-d') : \Carbon::parse($received_on)->format('Y-m-d');

        return self::create([
            'business_id' => $business_id,
            'type' => $type,
            'contact_id' => $contact_id,
            'transaction_id' => ! empty($transaction) ? $transaction->id : null,
            'location_id' => ! empty($transaction) ? $transaction->location_id : null,
            'cheque_number' => $data['cheque_number'] ?? null,
            'bank_name' => $data['cheque_bank_name'] ?? null,
            'amount' => $data['amount'],
            'received_on' => $received_on,
            'due_date' => $due_date,
            'status' => self::STATUS_PENDING,
            'account_id' => ! empty($data['account_id']) ? $data['account_id'] : null,
            'note' => $data['note'] ?? null,
            'created_by' => ! empty($user_id) ? $user_id : (auth()->check() ? auth()->user()->id : null),
        ]);
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    public function payment()
    {
        return $this->belongsTo(\App\TransactionPayment::class, 'payment_id');
    }

    public function account()
    {
        return $this->belongsTo(\App\Account::class, 'account_id');
    }

    public function isPending()
    {
        return $this->status == self::STATUS_PENDING;
    }
}
