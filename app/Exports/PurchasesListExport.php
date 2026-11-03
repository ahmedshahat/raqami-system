<?php

namespace App\Exports;

use Illuminate\Support\Facades\Lang;

/**
 * تقرير المشتريات (/purchases/export): نفس شكل تقرير المبيعات، بنصوص المشتريات وعمود حالة الشراء.
 * الكنترولر بيبعت رقم المرجع باسم invoice_no والمورد باسم customer عشان نفس الأعمدة تشتغل.
 */
class PurchasesListExport extends SalesListExport
{
    public function phrase(string $key, array $replace = []): string
    {
        return Lang::has('purchase_list.'.$key) ? __('purchase_list.'.$key, $replace) : parent::phrase($key, $replace);
    }

    public function columns(): array
    {
        $columns = parent::columns();
        $status = array_search('status', array_column($columns, 'key'));
        array_splice($columns, $status + 1, 0, [['key' => 'receive', 'label' => $this->phrase('col_receive'), 'width' => 15]]);

        return $columns;
    }

    public function records(): array
    {
        $records = parent::records();
        foreach ($this->rows->values() as $i => $row) {
            $records[$i]['values']['receive'] = __('lang_v1.'.$row->receive_status);
        }

        return $records;
    }
}
