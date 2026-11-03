<?php

namespace Modules\Construction\Entities;

use App\Account;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Construction\Support\ProtectsPostedConstructionDocument;

class ConstructionSubcontractAdvance extends Model
{
    use ProtectsPostedConstructionDocument;

    protected $table = 'construction_subcontract_advances';
    protected $guarded = ['id'];
    protected $casts = ['advance_date' => 'date', 'cancelled_at' => 'datetime'];

    public function subcontract() { return $this->belongsTo(ConstructionSubcontract::class, 'subcontract_id'); }
    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function account() { return $this->belongsTo(Account::class, 'account_id'); }
    public function accountingAccount() { return $this->belongsTo(AccountingAccount::class, 'accounting_account_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'subcontract_advance')->where('event', 'recorded');
    }
    public function cancellationAccountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'subcontract_advance')->where('event', 'cancelled');
    }
    public function isActive(): bool { return $this->status === 'recorded'; }
    protected function constructionAccountingSourceType(): string { return 'subcontract_advance'; }
}
