<?php

namespace App\Events;

use App\Transaction;
use Illuminate\Queue\SerializesModels;

class OpeningBalanceCreatedOrModified
{
    use SerializesModels;

    public $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }
}
