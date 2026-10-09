<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionCustomerRetentionRelease extends Model
{
    protected $table = 'construction_customer_retention_releases';
    protected $guarded = ['id'];
    protected $casts = ['release_date' => 'date', 'amount' => 'decimal:4', 'cancelled_at' => 'datetime'];

    public function certificate() { return $this->belongsTo(ConstructionCustomerCertificate::class, 'certificate_id'); }
    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function accountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'customer_retention_release')->where('event', 'recorded');
    }
    public function cancellationAccountingPosting()
    {
        return $this->hasOne(ConstructionAccountingPosting::class, 'source_id')
            ->where('source_type', 'customer_retention_release')->where('event', 'cancelled');
    }
    public function isActive(): bool { return $this->status === 'recorded'; }
}
