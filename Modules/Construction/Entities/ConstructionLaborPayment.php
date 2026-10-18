<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Construction\Support\ProtectsPostedConstructionDocument;

class ConstructionLaborPayment extends Model
{
    use ProtectsPostedConstructionDocument;
    protected $table = 'construction_labor_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'cancelled_at' => 'datetime',
    ];

    public function sheet() { return $this->belongsTo(ConstructionLaborSheet::class, 'labor_sheet_id'); }
    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function accountingAccount() { return $this->belongsTo(AccountingAccount::class, 'accounting_account_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'labor_payment')
            ->where('event', 'recorded');
    }
    public function cancellationAccountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'labor_payment')->where('event', 'cancelled');
    }
    public function isActive(): bool { return $this->status === 'recorded'; }
    protected function constructionAccountingSourceType(): string { return 'labor_payment'; }
}
