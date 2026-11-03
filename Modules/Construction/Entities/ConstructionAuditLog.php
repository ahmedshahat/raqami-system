<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionAuditLog extends Model
{
    public $timestamps = false;
    protected $table = 'construction_audit_logs';
    protected $guarded = ['id'];
    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function auditable() { return $this->morphTo(); }
}
