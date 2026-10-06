<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionCostCode extends Model
{
    public const CATEGORIES = ['material', 'labor', 'equipment', 'subcontract', 'overhead'];

    protected $table = 'construction_cost_codes';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

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
        return $this->hasMany(ConstructionBoqCostAllocation::class, 'cost_code_id');
    }
}
