<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ProjectStructure extends Model
{
    public const TYPES = ['site', 'building', 'phase', 'floor', 'zone'];

    protected $table = 'construction_project_structures';

    protected $guarded = ['id'];

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
