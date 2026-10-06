<?php

namespace Modules\Construction\Entities;

use App\Product;
use App\StockAdjustmentLine;
use App\Variation;
use Illuminate\Database\Eloquent\Model;

class ConstructionMaterialDocumentLine extends Model
{
    protected $table = 'construction_material_document_lines';

    protected $guarded = ['id'];

    public function document()
    {
        return $this->belongsTo(ConstructionMaterialDocument::class, 'document_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    public function boqItem()
    {
        return $this->belongsTo(ConstructionBoqItem::class, 'boq_item_id');
    }

    public function sourceIssueLine()
    {
        return $this->belongsTo(self::class, 'source_issue_line_id');
    }

    public function stockAdjustmentLine()
    {
        return $this->belongsTo(StockAdjustmentLine::class, 'stock_adjustment_line_id');
    }
}
