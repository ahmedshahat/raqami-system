<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionBoqCostAllocation extends Model
{
    protected $table = 'construction_boq_cost_allocations';

    protected $guarded = ['id'];

    protected $casts = ['estimated_cost' => 'decimal:4'];

    public function item()
    {
        return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id');
    }

    public function costCode()
    {
        return $this->belongsTo(ConstructionCostCode::class, 'cost_code_id');
    }

    public function version()
    {
        return $this->belongsTo(ConstructionBoqVersion::class, 'boq_version_id');
    }
}
