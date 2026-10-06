<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionBoqItem extends Model
{
    public const ROW_TYPES = ['section', 'item'];
    public const ITEM_KINDS = ['standard', 'optional', 'alternative', 'variation'];

    protected $table = 'construction_boq_items';

    protected $guarded = ['id'];

    protected $casts = [
        'contract_quantity' => 'decimal:4',
        'sales_unit_price' => 'decimal:4',
        'sales_total' => 'decimal:4',
    ];

    public function version()
    {
        return $this->belongsTo(ConstructionBoqVersion::class, 'boq_version_id');
    }

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function systemUnit()
    {
        return $this->belongsTo(\App\Unit::class, 'unit_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function allocations()
    {
        return $this->hasMany(ConstructionBoqCostAllocation::class, 'boq_item_id');
    }

    public function measurementItems()
    {
        return $this->hasMany(ConstructionMeasurementItem::class, 'boq_item_id');
    }

    public function certificateItems()
    {
        return $this->hasMany(ConstructionCustomerCertificateItem::class, 'boq_item_id');
    }

    public function approvedAdjustments()
    {
        return $this->hasMany(ConstructionContractAdjustment::class, 'original_boq_item_id')
            ->where('status', 'approved');
    }

    public function expenses()
    {
        return $this->hasMany(\App\Transaction::class, 'construction_boq_item_id')
            ->whereIn('type', ['expense', 'expense_refund']);
    }

    public function getAuthorizedQuantityAttribute(): float
    {
        return (float) $this->contract_quantity + (float) $this->approvedAdjustments()->sum('quantity');
    }

    public function getEstimatedCostAttribute(): float
    {
        return (float) $this->allocations->sum('estimated_cost');
    }

    public function getEstimatedMarginAttribute(): float
    {
        return (float) $this->sales_total - $this->estimated_cost;
    }
}
