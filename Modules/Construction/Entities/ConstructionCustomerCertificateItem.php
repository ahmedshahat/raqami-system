<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionCustomerCertificateItem extends Model
{
    protected $table = 'construction_customer_certificate_items';

    protected $guarded = ['id'];

    protected $casts = [
        'previous_quantity' => 'decimal:4',
        'submitted_quantity' => 'decimal:4',
        'approved_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'previous_amount' => 'decimal:4',
        'submitted_amount' => 'decimal:4',
        'approved_amount' => 'decimal:4',
    ];

    public function certificate()
    {
        return $this->belongsTo(ConstructionCustomerCertificate::class, 'certificate_id');
    }

    public function boqItem()
    {
        return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id');
    }
}
