<?php

namespace Modules\Construction\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * أي نص في ملفات Excel المصدّرة يتكتب كنص عادي، مش كمعادلة.
 * من غير كده وصف بند زي "=HYPERLINK(...)" كان هيتحول لمعادلة بتشتغل عند فتح الملف (Formula injection).
 * الأرقام بتتكتب أرقام عادي عشان الحسابات والتنسيق.
 */
class BindsTextSafely extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
