<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionLaborSheet extends Model
{
    protected $table = 'construction_labor_sheets';
    protected $guarded = ['id'];
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(ConstructionProject::class, 'project_id'); }
    public function lines() { return $this->hasMany(ConstructionLaborSheetLine::class, 'sheet_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function isEditable(): bool { return $this->status === 'draft'; }
}
