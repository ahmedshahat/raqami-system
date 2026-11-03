<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionContract extends Model
{
    public const TYPES = ['fixed_price', 'remeasurement', 'mixed', 'cost_plus'];

    public const STATUSES = ['draft', 'active', 'completed', 'cancelled'];

    protected $table = 'construction_contracts';

    protected $guarded = ['id'];

    protected $casts = [
        'signed_at' => 'date',
        'activated_at' => 'datetime',
        'is_primary' => 'boolean',
        'original_value' => 'decimal:4',
        'advance_payment_value' => 'decimal:4',
        'retention_percent' => 'decimal:4',
        'performance_bond_value' => 'decimal:4',
    ];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function boqVersion()
    {
        return $this->belongsTo(ConstructionBoqVersion::class, 'boq_version_id');
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function adjustments()
    {
        return $this->hasMany(ConstructionContractAdjustment::class, 'contract_id');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
