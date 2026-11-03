<?php

namespace Modules\Construction\Entities;

use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccTransMapping;

class ConstructionExpenseCancellation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['cancelled_at' => 'datetime'];

    public function expense() { return $this->belongsTo(Transaction::class, 'expense_transaction_id'); }
    public function refund() { return $this->belongsTo(Transaction::class, 'refund_transaction_id'); }
    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function mapping() { return $this->belongsTo(AccountingAccTransMapping::class, 'accounting_mapping_id'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
