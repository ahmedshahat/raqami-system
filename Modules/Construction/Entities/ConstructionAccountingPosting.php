<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccTransMapping;

class ConstructionAccountingPosting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function mapping()
    {
        return $this->belongsTo(AccountingAccTransMapping::class, 'accounting_mapping_id');
    }
}
