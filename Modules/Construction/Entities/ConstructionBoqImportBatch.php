<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionBoqImportBatch extends Model
{
    protected $table = 'construction_boq_import_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'rows_json' => 'array',
        'expires_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function version()
    {
        return $this->belongsTo(ConstructionBoqVersion::class, 'boq_version_id');
    }
}
