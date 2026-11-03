<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionMeasurementItem extends Model
{
    protected $table = 'construction_measurement_items';

    protected $guarded = ['id'];

    protected $casts = [
        'executed_quantity' => 'decimal:4',
        'approved_quantity' => 'decimal:4',
    ];

    public function measurement()
    {
        return $this->belongsTo(ConstructionMeasurement::class, 'measurement_id');
    }

    public function boqItem()
    {
        return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id');
    }

    public function structure()
    {
        return $this->belongsTo(ProjectStructure::class, 'structure_id');
    }

    public function certificate()
    {
        return $this->belongsTo(ConstructionCustomerCertificate::class, 'certificate_id');
    }
}
