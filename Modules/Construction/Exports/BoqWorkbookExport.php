<?php

namespace Modules\Construction\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoqWorkbookExport implements FromArray, WithColumnWidths, WithStyles, WithColumnFormatting, WithTitle
{
    public function __construct(private readonly Collection $items, private readonly string $sheetTitle = 'بنود المشروع')
    {
    }

    public static function template(): self
    {
        return new self(collect([
            (object) ['row_type' => 'section', 'code' => '01', 'description' => 'الأعمال الخرسانية', 'parent' => null, 'unit' => null, 'contract_quantity' => 0, 'sales_unit_price' => 0, 'sales_total' => 0, 'item_kind' => 'standard', 'notes' => 'احذف بيانات المثال أو عدّلها'],
            (object) ['row_type' => 'item', 'code' => '01.001', 'description' => 'خرسانة مسلحة للأساسات', 'parent' => (object) ['code' => '01'], 'unit' => 'م3', 'contract_quantity' => 100, 'sales_unit_price' => 2500, 'sales_total' => 250000, 'item_kind' => 'standard', 'notes' => null],
        ]), 'نموذج استيراد بنود المشروع');
    }

    public function array(): array
    {
        $rows = [[
            'نوع السطر', 'كود البند', 'بيان الأعمال', 'كود الباب الرئيسي', 'الوحدة',
            'كمية التعاقد', 'سعر التعاقد', 'تصنيف البند', 'ملاحظات', 'إجمالي البند',
        ]];

        foreach ($this->items as $item) {
            $rows[] = [
                $item->row_type === 'section' ? 'باب' : 'بند',
                $item->code,
                $item->description,
                $item->parent?->code,
                $item->unit,
                (float) $item->contract_quantity,
                (float) $item->sales_unit_price,
                $this->kindLabel($item->item_kind),
                $item->notes,
                $item->row_type === 'item' ? (float) $item->sales_total : 0,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173B66']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A:J')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C:C')->getAlignment()->setWrapText(true);
        $sheet->getStyle('I:I')->getAlignment()->setWrapText(true);
        $sheet->setAutoFilter('A1:J'.max(2, $sheet->getHighestRow()));
        $sheet->getRowDimension(1)->setRowHeight(28);

        return [];
    }

    public function columnFormats(): array
    {
        $quantityFormat = $this->excelNumberFormat((int) session('business.quantity_precision', 2));
        $currencyFormat = $this->excelNumberFormat((int) session('business.currency_precision', 2));

        return ['F' => $quantityFormat, 'G' => $currencyFormat, 'J' => $currencyFormat];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 16, 'C' => 48, 'D' => 18, 'E' => 12, 'F' => 17, 'G' => 17, 'H' => 16, 'I' => 32, 'J' => 18];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'optional' => 'اختياري',
            'alternative' => 'بديل',
            'variation' => 'أعمال إضافية',
            default => 'أساسي',
        };
    }

    private function excelNumberFormat(int $precision): string
    {
        $precision = max(0, min(4, $precision));

        return '#,##0'.($precision > 0 ? '.'.str_repeat('0', $precision) : '');
    }
}
