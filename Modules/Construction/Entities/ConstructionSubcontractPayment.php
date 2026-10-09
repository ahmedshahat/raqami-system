<?php

namespace Modules\Construction\Entities;

use App\Account;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccount;

class ConstructionSubcontractPayment extends Model
{
    protected $table = 'construction_subcontract_payments';
    protected $guarded = ['id'];
    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:4', 'cancelled_at' => 'datetime'];

    public function certificate() { return $this->belongsTo(ConstructionSubcontractCertificate::class, 'certificate_id'); }
    public function subcontract() { return $this->belongsTo(ConstructionSubcontract::class, 'subcontract_id'); }
    public function account() { return $this->belongsTo(Account::class, 'account_id'); }
    public function accountingAccount() { return $this->belongsTo(AccountingAccount::class, 'accounting_account_id'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'subcontract_payment')
            ->where('event', 'recorded');
    }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function isActive(): bool { return $this->status === 'recorded'; }
}
