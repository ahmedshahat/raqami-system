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

/**
 * نموذج استيراد بنود عرض السعر. مفيهوش كود ولا إجمالي: الكود بيتولد عند الحفظ والإجمالي = الكمية × السعر.
 * الاستيراد بيتعرف على الأعمدة من عناوينها، فالملفات القديمة اللي فيها العمودين دول بتشتغل برضه.
 */
class QuoteItemsWorkbookExport extends BindsTextSafely implements FromArray, WithCustomValueBinder, WithColumnWidths, WithStyles, WithColumnFormatting, WithTitle
{
    public function __construct(private readonly Collection $items, private readonly string $sheetTitle = 'بنود عرض السعر')
    {
    }

    public static function template(): self
    {
        return new self(collect([
            (object) [
                'description' => 'خرسانة مسلحة للأساسات',
                'unit' => 'م3',
                'quantity' => 100,
                'unit_price' => 2500,
                'notes' => 'احذف بيانات المثال أو عدّلها',
            ],
        ]), 'نموذج استيراد عرض السعر');
    }

    public function array(): array
    {
        $rows = [[
            'بيان الأعمال', 'الوحدة', 'الكمية', 'سعر الوحدة', 'ملاحظات',
        ]];

        foreach ($this->items as $item) {
            $rows[] = [
                $item->description,
                $item->unit,
                (float) $item->quantity,
                (float) $item->unit_price,
                $item->notes,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D237F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A:E')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A:A')->getAlignment()->setWrapText(true);
        $sheet->getStyle('E:E')->getAlignment()->setWrapText(true);
        $sheet->setAutoFilter('A1:E'.max(2, $sheet->getHighestRow()));
        $sheet->getRowDimension(1)->setRowHeight(28);

        return [];
    }

    public function columnFormats(): array
    {
        $quantityFormat = $this->excelNumberFormat((int) session('business.quantity_precision', 2));
        $currencyFormat = $this->excelNumberFormat((int) session('business.currency_precision', 2));

        return ['C' => $quantityFormat, 'D' => $currencyFormat];
    }

    public function columnWidths(): array
    {
        return ['A' => 48, 'B' => 14, 'C' => 16, 'D' => 17, 'E' => 34];
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
