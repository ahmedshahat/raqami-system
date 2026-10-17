<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionQuoteImportBatch extends Model
{
    protected $table = 'construction_quote_import_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'rows_json' => 'array',
        'expires_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function quote()
    {
        return $this->belongsTo(ConstructionQuote::class, 'quote_id');
    }
}
