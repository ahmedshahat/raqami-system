<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionSubcontractCertificateItem extends Model
{
    protected $table = 'construction_subcontract_certificate_items';
    protected $guarded = ['id'];
    protected $casts = [
        'contract_quantity' => 'decimal:4',
        'unit_rate' => 'decimal:4',
        'previous_quantity' => 'decimal:4',
        'current_quantity' => 'decimal:4',
        'current_value' => 'decimal:4',
    ];

    public function certificate() { return $this->belongsTo(ConstructionSubcontractCertificate::class, 'certificate_id'); }
    public function subcontractItem() { return $this->belongsTo(ConstructionSubcontractItem::class, 'subcontract_item_id'); }
    public function cumulativeQuantity(): float { return (float) $this->previous_quantity + (float) $this->current_quantity; }
}
