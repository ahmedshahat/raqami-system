<?php

namespace Modules\Construction\Entities;

use App\Contact;
use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionSubcontract extends Model
{
    protected $table = 'construction_subcontracts';
    protected $guarded = ['id'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];

    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function subcontractor() { return $this->belongsTo(Contact::class, 'subcontractor_id'); }
    public function items() { return $this->hasMany(ConstructionSubcontractItem::class, 'subcontract_id')->orderBy('sort_order')->orderBy('id'); }
    public function certificates() { return $this->hasMany(ConstructionSubcontractCertificate::class, 'subcontract_id')->latest('certificate_date')->latest('id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function isEditable(): bool { return $this->status === 'draft'; }
    public function retentionValue(): float { return round((float) $this->total_value * (float) $this->retention_percent / 100, 4); }
}
