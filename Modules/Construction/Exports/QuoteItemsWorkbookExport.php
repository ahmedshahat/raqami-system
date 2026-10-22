<?php

namespace Modules\Construction\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Modules\Construction\Exports\Concerns\BindsTextSafely;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuoteItemsWorkbookExport extends BindsTextSafely implements FromArray, WithCustomValueBinder, WithColumnWidths, WithStyles, WithColumnFormatting, WithTitle
{
    public function __construct(private readonly Collection $items, private readonly string $sheetTitle = 'بنود عرض السعر')
    {
    }

    public static function template(): self
    {
        return new self(collect([
            (object) [
                'code' => 'ITM-001',
                'description' => 'خرسانة مسلحة للأساسات',
                'unit' => 'م3',
                'quantity' => 100,
                'unit_price' => 2500,
                'notes' => 'احذف بيانات المثال أو عدّلها',
                'total' => 250000,
            ],
        ]), 'نموذج استيراد عرض السعر');
    }

    public function array(): array
    {
        $rows = [[
            'كود البند', 'بيان الأعمال', 'الوحدة', 'الكمية', 'سعر الوحدة', 'ملاحظات', 'إجمالي البند',
        ]];

        foreach ($this->items as $item) {
            $rows[] = [
                $item->code,
                $item->description,
                $item->unit,
                (float) $item->quantity,
                (float) $item->unit_price,
                $item->notes,
                (float) $item->total,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D237F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A:G')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);
        $sheet->getStyle('F:F')->getAlignment()->setWrapText(true);
        $sheet->setAutoFilter('A1:G'.max(2, $sheet->getHighestRow()));
        $sheet->getRowDimension(1)->setRowHeight(28);

        return [];
    }

    public function columnFormats(): array
    {
        $quantityFormat = $this->excelNumberFormat((int) session('business.quantity_precision', 2));
        $currencyFormat = $this->excelNumberFormat((int) session('business.currency_precision', 2));

        return ['D' => $quantityFormat, 'E' => $currencyFormat, 'G' => $currencyFormat];
    }

    public function columnWidths(): array
    {
        return ['A' => 17, 'B' => 48, 'C' => 14, 'D' => 16, 'E' => 17, 'F' => 34, 'G' => 18];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    private function excelNumberFormat(int $precision): string
    {
        $precision = max(0, min(4, $precision));

        return '#,##0'.($precision > 0 ? '.'.str_repeat('0', $precision) : '');
    }
}
