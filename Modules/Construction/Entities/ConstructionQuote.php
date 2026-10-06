<?php

namespace Modules\Construction\Entities;

use App\Contact;
use Illuminate\Database\Eloquent\Model;

class ConstructionQuote extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    protected $table = 'construction_quotes';

    protected $guarded = ['id'];

    protected $casts = ['quote_date' => 'date', 'total' => 'decimal:4'];

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(ConstructionQuoteItem::class, 'quote_id');
    }

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function refreshTotal(): void
    {
        $this->forceFill(['total' => $this->items()->sum('total')])->save();
    }
}
