<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionSubcontractRetentionRelease extends Model
{
    protected $table = 'construction_subcontract_retention_releases';
    protected $guarded = ['id'];
    protected $casts = ['release_date' => 'date', 'amount' => 'decimal:4', 'cancelled_at' => 'datetime'];

    public function certificate() { return $this->belongsTo(ConstructionSubcontractCertificate::class, 'certificate_id'); }
    public function subcontract() { return $this->belongsTo(ConstructionSubcontract::class, 'subcontract_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'subcontract_retention_release')
            ->where('event', 'recorded');
    }
    public function cancellationAccountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'subcontract_retention_release')
            ->where('event', 'cancelled');
    }
    public function isActive(): bool { return $this->status === 'recorded'; }
}
