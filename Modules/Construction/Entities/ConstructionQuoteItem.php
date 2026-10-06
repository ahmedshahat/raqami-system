<?php

namespace Modules\Construction\Entities;

use App\Unit;
use Illuminate\Database\Eloquent\Model;

class ConstructionQuoteItem extends Model
{
    protected $table = 'construction_quote_items';

    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'total' => 'decimal:4'];

    public function quote()
    {
        return $this->belongsTo(ConstructionQuote::class, 'quote_id');
    }

    public function systemUnit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
