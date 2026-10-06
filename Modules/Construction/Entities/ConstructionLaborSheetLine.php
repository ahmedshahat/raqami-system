<?php

namespace Modules\Construction\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class ConstructionLaborSheetLine extends Model
{
    protected $table = 'construction_labor_sheet_lines';
    protected $guarded = ['id'];

    public function sheet() { return $this->belongsTo(ConstructionLaborSheet::class, 'sheet_id'); }
    public function worker() { return $this->belongsTo(User::class, 'user_id'); }
    public function boqItem() { return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id'); }
}
