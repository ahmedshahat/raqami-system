<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionMeasurement extends Model
{
    public const STATUSES = ['draft', 'approved', 'rejected'];

    protected $table = 'construction_measurements';

    protected $guarded = ['id'];

    protected $casts = [
        'measurement_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(ConstructionMeasurementItem::class, 'measurement_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
