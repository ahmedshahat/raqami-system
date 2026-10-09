<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionCustomerCertificate extends Model
{
    public const STATUSES = ['draft', 'submitted', 'partially_approved', 'approved', 'posted', 'paid', 'cancelled'];

    protected $table = 'construction_customer_certificates';

    protected $guarded = ['id'];

    protected $casts = [
        'certificate_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'previous_gross' => 'decimal:4',
        'current_submitted_gross' => 'decimal:4',
        'current_approved_gross' => 'decimal:4',
        'retention_value' => 'decimal:4',
        'retention_due_date' => 'date',
        'advance_recovery_value' => 'decimal:4',
        'other_deductions_value' => 'decimal:4',
        'tax_value' => 'decimal:4',
        'net_due' => 'decimal:4',
    ];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function boqVersion()
    {
        return $this->belongsTo(ConstructionBoqVersion::class, 'boq_version_id');
    }

    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function measurement()
    {
        return $this->belongsTo(ConstructionMeasurement::class, 'measurement_id');
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Transaction::class, 'invoice_transaction_id');
    }

    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'customer_certificate')
            ->where('event', 'invoiced');
    }

    public function items()
    {
        return $this->hasMany(ConstructionCustomerCertificateItem::class, 'certificate_id');
    }

    public function retentionReleases()
    {
        return $this->hasMany(ConstructionCustomerRetentionRelease::class, 'certificate_id');
    }

    public function activeRetentionReleases()
    {
        return $this->retentionReleases()->where('status', 'recorded');
    }

    public function releasedRetentionValue(): float
    {
        return round((float) $this->activeRetentionReleases()->sum('amount'), 4);
    }

    public function retentionRemainingValue(): float
    {
        return max(0, round((float) $this->retention_value - $this->releasedRetentionValue(), 4));
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function recalculate(bool $useApproved = false): void
    {
        $gross = (float) $this->items()->sum($useApproved ? 'approved_amount' : 'submitted_amount');
        $retention = round($gross * ((float) $this->retention_percent / 100), 4);
        $tax = round($gross * ((float) $this->tax_percent / 100), 4);
        $net = $gross + $tax - $retention - (float) $this->advance_recovery_value - (float) $this->other_deductions_value;

        $this->forceFill([
            $useApproved ? 'current_approved_gross' : 'current_submitted_gross' => $gross,
            'retention_value' => $retention,
            'tax_value' => $tax,
            'net_due' => round($net, 4),
        ])->save();
    }
}
