<?php

namespace Modules\Construction\Entities;

use App\BusinessLocation;
use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionMaterialDocument extends Model
{
    protected $table = 'construction_material_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'document_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function lines()
    {
        return $this->hasMany(ConstructionMaterialDocumentLine::class, 'document_id');
    }

    public function parentIssue()
    {
        return $this->belongsTo(self::class, 'parent_issue_id');
    }

    public function returns()
    {
        return $this->hasMany(self::class, 'parent_issue_id')->where('type', 'return');
    }

    public function stockAdjustment()
    {
        return $this->belongsTo(Transaction::class, 'stock_adjustment_transaction_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
