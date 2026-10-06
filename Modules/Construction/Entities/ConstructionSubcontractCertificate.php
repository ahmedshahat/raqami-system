<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionSubcontractCertificate extends Model
{
    protected $table = 'construction_subcontract_certificates';
    protected $guarded = ['id'];
    protected $casts = [
        'certificate_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public function subcontract() { return $this->belongsTo(ConstructionSubcontract::class, 'subcontract_id'); }
    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function items() { return $this->hasMany(ConstructionSubcontractCertificateItem::class, 'certificate_id')->orderBy('id'); }
    public function payments() { return $this->hasMany(ConstructionSubcontractPayment::class, 'certificate_id')->latest('payment_date')->latest('id'); }
    public function activePayments() { return $this->hasMany(ConstructionSubcontractPayment::class, 'certificate_id')->where('status', 'recorded'); }
    public function retentionReleases() { return $this->hasMany(ConstructionSubcontractRetentionRelease::class, 'certificate_id')->latest('release_date')->latest('id'); }
    public function activeRetentionReleases() { return $this->hasMany(ConstructionSubcontractRetentionRelease::class, 'certificate_id')->where('status', 'recorded'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function isEditable(): bool { return $this->status === 'draft'; }
    public function paidValue(): float { return round((float) $this->activePayments()->sum('amount'), 4); }
    public function releasedRetentionValue(): float { return round((float) $this->activeRetentionReleases()->sum('amount'), 4); }
    public function retentionRemainingValue(): float { return round(max(0, (float) $this->retention_value - $this->releasedRetentionValue()), 4); }
    public function payableValue(): float { return round((float) $this->net_value + $this->releasedRetentionValue(), 4); }
    public function remainingValue(): float { return round(max(0, $this->payableValue() - $this->paidValue()), 4); }
    public function paymentStatus(): string
    {
        $paid = $this->paidValue();
        if ($paid <= 0.0001) return 'unpaid';
        return $this->remainingValue() <= 0.0001 ? 'paid' : 'partially_paid';
    }
}
