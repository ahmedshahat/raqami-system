<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionSubcontractItem extends Model
{
    protected $table = 'construction_subcontract_items';
    protected $guarded = ['id'];
    protected $casts = ['quantity' => 'decimal:4', 'unit_rate' => 'decimal:4', 'total_value' => 'decimal:4'];

    public function subcontract() { return $this->belongsTo(ConstructionSubcontract::class, 'subcontract_id'); }
    public function boqItem() { return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id'); }
}
