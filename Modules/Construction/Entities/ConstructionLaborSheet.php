<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Construction\Support\ProtectsPostedConstructionDocument;

class ConstructionLaborSheet extends Model
{
    use ProtectsPostedConstructionDocument;
    protected $table = 'construction_labor_sheets';
    protected $guarded = ['id'];
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function lines() { return $this->hasMany(ConstructionLaborSheetLine::class, 'sheet_id'); }
    public function payments() { return $this->hasMany(ConstructionLaborPayment::class, 'labor_sheet_id')->latest('payment_date')->latest('id'); }
    public function activePayments() { return $this->hasMany(ConstructionLaborPayment::class, 'labor_sheet_id')->where('status', 'recorded'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'labor_sheet')->where('event', 'approval');
    }
    public function cancellationAccountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'labor_sheet')->where('event', 'cancelled');
    }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function isEditable(): bool { return $this->status === 'draft'; }
    public function totalCost(): float { return round((float) $this->lines()->sum('total_cost'), 4); }
    public function paidValue(): float { return round((float) $this->activePayments()->sum('amount'), 4); }
    public function remainingValue(): float { return round(max(0, $this->totalCost() - $this->paidValue()), 4); }
    public function paymentStatus(): string
    {
        $paid = $this->paidValue();
        if ($paid <= 0.0001) return 'unpaid';
        return $this->remainingValue() <= 0.0001 ? 'paid' : 'partially_paid';
    }
    protected function constructionAccountingSourceType(): string { return 'labor_sheet'; }
}
