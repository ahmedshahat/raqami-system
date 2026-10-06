<?php

namespace Modules\Construction\Entities;

use App\Contact;
use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionProject extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'on_hold', 'completed', 'cancelled'];

    protected $table = 'construction_projects';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function quote()
    {
        return $this->belongsTo(ConstructionQuote::class, 'quote_id');
    }

    public function consultantContact()
    {
        return $this->belongsTo(Contact::class, 'consultant_contact_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts()
    {
        return $this->hasMany(ConstructionContract::class, 'project_id');
    }

    public function primaryContract()
    {
        return $this->hasOne(ConstructionContract::class, 'project_id')->where('is_primary', true);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'construction_project_members', 'project_id', 'user_id')
            ->withPivot(['business_id', 'access_level', 'assigned_by'])
            ->withTimestamps();
    }

    public function structures()
    {
        return $this->hasMany(ProjectStructure::class, 'project_id');
    }

    public function boqVersions()
    {
        return $this->hasMany(ConstructionBoqVersion::class, 'project_id');
    }

    public function approvedBoq()
    {
        return $this->hasOne(ConstructionBoqVersion::class, 'project_id')
            ->where('status', 'approved')
            ->latestOfMany('version_number');
    }

    public function measurements()
    {
        return $this->hasMany(ConstructionMeasurement::class, 'project_id');
    }

    public function customerCertificates()
    {
        return $this->hasMany(ConstructionCustomerCertificate::class, 'project_id');
    }

    public function contractAdjustments()
    {
        return $this->hasMany(ConstructionContractAdjustment::class, 'project_id');
    }

    public function expenses()
    {
        return $this->hasMany(Transaction::class, 'construction_project_id')
            ->whereIn('type', ['expense', 'expense_refund']);
    }

    public function materialDocuments()
    {
        return $this->hasMany(ConstructionMaterialDocument::class, 'project_id');
    }

    public function subcontracts()
    {
        return $this->hasMany(ConstructionSubcontract::class, 'project_id');
    }
}
