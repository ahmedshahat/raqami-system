<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionBoqVersion extends Model
{
    public const STATUSES = ['draft', 'approved', 'superseded'];

    protected $table = 'construction_boq_versions';

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
        'sales_total' => 'decimal:4',
        'estimated_cost_total' => 'decimal:4',
    ];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(ConstructionBoqItem::class, 'boq_version_id');
    }

    public function allocations()
    {
        return $this->hasMany(ConstructionBoqCostAllocation::class, 'boq_version_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function contracts()
    {
        return $this->hasMany(ConstructionContract::class, 'boq_version_id');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function refreshTotals(): void
    {
        $this->forceFill([
            'sales_total' => $this->items()->where('row_type', 'item')->sum('sales_total'),
            'estimated_cost_total' => $this->allocations()->sum('estimated_cost'),
        ])->save();
    }
}
