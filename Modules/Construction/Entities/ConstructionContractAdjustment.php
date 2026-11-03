<?php

namespace Modules\Construction\Entities;

use App\Unit;
use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionContractAdjustment extends Model
{
    public const STATUSES = ['draft', 'approved'];

    protected $table = 'construction_contract_adjustments';
    protected $guarded = ['id'];
    protected $casts = [
        'adjustment_date' => 'date',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function contract() { return $this->belongsTo(ConstructionContract::class, 'contract_id'); }
    public function originalItem() { return $this->belongsTo(ConstructionBoqItem::class, 'original_boq_item_id'); }
    public function boqItem() { return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id'); }
    public function systemUnit() { return $this->belongsTo(Unit::class, 'unit_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
