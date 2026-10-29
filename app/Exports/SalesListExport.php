<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * تصدير قائمة المبيعات (/sells/v2) لكل الفترة المحددة.
 * Excel: رأس باسم النشاط والفترة، مؤشرات بتتحسب من الجدول نفسه (SUBTOTAL، فبتتغير مع فلتر Excel)،
 * جدول بفلتر وتجميد للرأس وصف إجمالي، وإعدادات طباعة جاهزة. CSV: نفس الأعمدة بدون تنسيق.
 * كل النصوص بتتكتب كنص صريح، عشان اسم عميل زي "=HYPERLINK(...)" ما يتحولش لمعادلة.
 */
class SalesListExport
{
    // ألوان رقمي (public/css/raqmi.css) من غير #
    private const BRAND = '22177A';
    private const BRAND_TINT = 'ECEAFB';
    private const INK = '1B1F2A';
    private const MUTED = '5B6475';
    private const LINE = 'DDE1E8';
    private const ZEBRA = 'F7F8FB';
    private const TONES = [
        'paid' => ['0A6252', 'E0F3EF'],
        'partial' => ['0A5A82', 'E1F0F8'],
        'due' => ['7C4A00', 'FFF1D3'],
        'overdue' => ['AE231B', 'FDE8E6'],
    ];

    private const HEAD_ROW = 8;

    public function __construct(private Collection $rows, private array $meta)
    {
    }

    /**
     * صفوف الجدول بالترتيب: [قيمة لكل عمود] + الحالة عشان اللون.
     */
    public function columns(): array
    {
        return [
            ['key' => 'n', 'label' => '#', 'width' => 6, 'type' => 'int'],
            ['key' => 'invoice', 'label' => __('sell_list.col_invoice'), 'width' => 16],
            ['key' => 'date', 'label' => __('sell_list.xl_date'), 'width' => 18, 'type' => 'date'],
            ['key' => 'customer', 'label' => __('sell_list.col_customer'), 'width' => 28],
            ['key' => 'mobile', 'label' => __('sell_list.xl_mobile'), 'width' => 16],
            ['key' => 'location', 'label' => __('sell_list.col_location'), 'width' => 18],
            ['key' => 'total', 'label' => __('sell_list.col_total'), 'width' => 15, 'type' => 'money', 'sum' => true],
            ['key' => 'paid', 'label' => __('sell_list.col_paid'), 'width' => 15, 'type' => 'money', 'sum' => true],
            ['key' => 'due', 'label' => __('sell_list.col_due'), 'width' => 15, 'type' => 'money', 'sum' => true],
            ['key' => 'returns', 'label' => __('sell_list.kpi_returns'), 'width' => 13, 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => __('sell_list.col_status'), 'width' => 15],
            ['key' => 'method', 'label' => __('sell_list.col_method'), 'width' => 18],
            ['key' => 'added_by', 'label' => __('sell_list.col_added_by'), 'width' => 18],
            ['key' => 'note', 'label' => __('sell_list.col_sell_note'), 'width' => 30],
        ];
    }

    public function records(): array
    {
        $labels = [
            'paid' => __('lang_v1.paid'),
            'partial' => __('lang_v1.partial'),
            'due' => __('lang_v1.due'),
            'overdue' => __('lang_v1.overdue'),
        ];
        $methods = $this->meta['methods'] ?? [];

        $records = [];
        foreach ($this->rows->values() as $i => $row) {
            $total = (float) $row->final_total;
            $paid = (float) $row->paid;
            $status = $row->payment_status;
            if (in_array($status, ['due', 'partial']) && $this->isOverdue($row)) {
                $status = 'overdue';
            }

            $rowMethods = array_filter(explode(',', (string) $row->methods));
            $customer = trim((string) $row->customer);
            if (! empty($row->supplier_business_name)) {
                $customer = trim($row->supplier_business_name.($customer !== '' ? ' - '.$customer : ''));
            }

            $records[] = [
                'tone' => $status,
                'values' => [
                    'n' => $i + 1,
                    'invoice' => (string) $row->invoice_no,
                    'date' => $row->transaction_date,
                    'customer' => $customer,
                    'mobile' => (string) $row->mobile,
                    'location' => (string) $row->location,
                    'total' => $total,
                    'paid' => $paid,
                    'due' => max($total - $paid, 0),
                    'returns' => (float) $row->returns,
                    'status' => $labels[$status] ?? (string) $status,
                    'method' => count($rowMethods) > 1 ? __('lang_v1.checkout_multi_pay') : (string) ($methods[reset($rowMethods)] ?? reset($rowMethods) ?: ''),
                    'added_by' => (string) $row->added_by,
                    'note' => trim(strip_tags((string) $row->additional_notes)),
                ],
            ];
        }

        return $records;
    }

    private function isOverdue($row): bool
    {
        if (empty($row->pay_term_number) || empty($row->pay_term_type)) {
            return false;
        }
        $date = Carbon::parse($row->transaction_date);
        $due = $row->pay_term_type === 'days' ? $date->addDays($row->pay_term_number) : $date->addMonths($row->pay_term_number);

        return $due->lt(Carbon::today());
    }

    public function periodText(): string
    {
        if (! empty($this->meta['selected'])) {
            return __('sell_list.xl_selected_only');
        }
        if (empty($this->meta['start']) || empty($this->meta['end'])) {
            return __('sell_list.xl_all_dates');
        }

        // التواريخ معزولة اتجاهيًا (LRI…PDI) عشان ما تتقلبش جوه جملة عربي
        $iso = fn ($date) => "\u{2066}{$date}\u{2069}";

        return __('sell_list.range', ['start' => $iso($this->meta['start']), 'end' => $iso($this->meta['end'])]);
    }

    public function spreadsheet(): Spreadsheet
    {
        $columns = $this->columns();
        $records = $this->records();
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $col = [];
        foreach ($columns as $i => $column) {
            $col[$column['key']] = Coordinate::stringFromColumnIndex($i + 1);
        }

        $book = new Spreadsheet();
        $book->getProperties()->setTitle(__('sell_list.xl_title'))->setCreator((string) ($this->meta['business'] ?? ''));
        $book->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10)->getColor()->setRGB(self::INK);

        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(__('sell_list.title'), 0, 31));
        $sheet->setRightToLeft(app()->getLocale() === 'ar');
        $sheet->setShowGridlines(false);

        // ─── الرأس ───
        $this->text($sheet, 'A1', (string) ($this->meta['business'] ?? ''));
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setSize(18)->setBold(true)->getColor()->setRGB(self::BRAND);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $this->text($sheet, 'A2', __('sell_list.xl_title').'  ·  '.$this->periodText());
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $note = trim((string) ($this->meta['note'] ?? ''));
        $this->text($sheet, 'A3', ($note !== '' ? __('sell_list.active_filters').': '.$note.'  ·  ' : '').
            __('sell_list.xl_generated', ['date' => now()->format('Y-m-d H:i'), 'user' => (string) ($this->meta['user'] ?? '')]));
        $sheet->mergeCells("A3:{$last}3");
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB(self::MUTED);
        $sheet->getStyle("A1:{$last}3")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // ─── الجدول ───
        $head = self::HEAD_ROW;
        $first = $head + 1;
        $end = max($first, $head + count($records));
        $totalRow = $end + 1;

        foreach ($columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $this->text($sheet, $letter.$head, $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }
        $sheet->getStyle("A{$head}:{$last}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($head)->setRowHeight(26);

        $precision = max(0, min(4, (int) ($this->meta['precision'] ?? 2)));
        $moneyFormat = '#,##0'.($precision ? '.'.str_repeat('0', $precision) : '').';[Red]-#,##0'.($precision ? '.'.str_repeat('0', $precision) : '');

        $r = $first;
        foreach ($records as $record) {
            foreach ($columns as $i => $column) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
                $value = $record['values'][$column['key']];
                $type = $column['type'] ?? 'text';
                if ($type === 'date') {
                    $sheet->setCellValue($cell, ExcelDate::PHPToExcel(Carbon::parse($value)));
                } elseif ($type === 'money' || $type === 'int') {
                    $sheet->setCellValue($cell, $value);
                } else {
                    $this->text($sheet, $cell, (string) $value);
                }
            }
            $tone = self::TONES[$record['tone']] ?? null;
            if ($tone) {
                $sheet->getStyle($col['status'].$r)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $tone[0]]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $tone[1]]],
                ]);
            }
            $r++;
        }
        if (empty($records)) {
            $this->text($sheet, "A{$first}", __('sell_list.empty'));
            $sheet->mergeCells("A{$first}:{$last}{$first}");
            $sheet->getStyle("A{$first}")->getFont()->getColor()->setRGB(self::MUTED);
        }

        $body = "A{$first}:{$last}{$end}";
        $sheet->getStyle($body)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($body)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $sheet->getStyle("A{$first}:{$last}{$end}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $sheet->getStyle($col['date']."{$first}:".$col['date'].$end)->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
        $sheet->getStyle($col['invoice']."{$first}:".$col['invoice'].$end)->getFont()->setBold(true);
        $sheet->getStyle($col['note']."{$first}:".$col['note'].$end)->getAlignment()->setWrapText(true);
        foreach (['n', 'date', 'mobile', 'status'] as $key) {
            $sheet->getStyle($col[$key]."{$head}:".$col[$key].$end)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach ($columns as $column) {
            if (($column['type'] ?? '') === 'money') {
                $range = $col[$column['key']]."{$first}:".$col[$column['key']].$totalRow;
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode($moneyFormat);
            }
        }

        // صفوف بالتبادل (تنسيق شرطي عشان يفضل مظبوط بعد الفرز والفلترة)، من غير خلية الحالة الملونة
        if (! empty($records)) {
            $zebra = new Conditional();
            $zebra->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=0');
            $zebra->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB(self::ZEBRA);
            $statusIndex = array_search('status', array_column($columns, 'key')) + 1;
            $ranges = ['A'.$first.':'.Coordinate::stringFromColumnIndex($statusIndex - 1).$end];
            if ($statusIndex < count($columns)) {
                $ranges[] = Coordinate::stringFromColumnIndex($statusIndex + 1).$first.':'.$last.$end;
            }
            foreach ($ranges as $range) {
                $sheet->getStyle($range)->setConditionalStyles([clone $zebra]);
            }
        }

        // ─── صف الإجمالي: SUBTOTAL بيحسب الصفوف الظاهرة بس بعد فلتر Excel ───
        $this->text($sheet, "A{$totalRow}", __('sell_list.xl_total_row'));
        $sheet->mergeCells("A{$totalRow}:".$col['location'].$totalRow);
        foreach ($columns as $column) {
            if (! empty($column['sum'])) {
                $letter = $col[$column['key']];
                $sheet->setCellValue($letter.$totalRow, "=SUBTOTAL(109,{$letter}{$first}:{$letter}{$end})");
            }
        }
        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::BRAND]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_TINT]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRAND]]],
        ]);
        $sheet->getRowDimension($totalRow)->setRowHeight(24);

        // ─── المؤشرات فوق الجدول ───
        $ref = function ($key) use ($col, $first, $end) {
            return $col[$key].$first.':'.$col[$key].$end;
        };
        $count = 'SUBTOTAL(103,'.$col['invoice'].$first.':'.$col['invoice'].$end.')';
        $kpis = [
            [__('sell_list.kpi_total'), '=SUBTOTAL(109,'.$ref('total').')', self::BRAND, self::BRAND_TINT, $moneyFormat],
            [__('sell_list.kpi_paid'), '=SUBTOTAL(109,'.$ref('paid').')', self::TONES['paid'][0], self::TONES['paid'][1], $moneyFormat],
            [__('sell_list.kpi_due'), '=SUBTOTAL(109,'.$ref('due').')', self::TONES['due'][0], self::TONES['due'][1], $moneyFormat],
            [__('sell_list.kpi_returns'), '=SUBTOTAL(109,'.$ref('returns').')', self::TONES['overdue'][0], self::TONES['overdue'][1], $moneyFormat],
            [__('sell_list.kpi_invoices'), empty($records) ? 0 : '='.$count, self::TONES['partial'][0], self::TONES['partial'][1], '#,##0'],
            [__('sell_list.kpi_average'), empty($records) ? 0 : '=IFERROR(SUBTOTAL(109,'.$ref('total').')/'.$count.',0)', self::MUTED, 'EEF0F4', $moneyFormat],
        ];
        $c = 1;
        foreach ($kpis as $kpi) {
            $from = Coordinate::stringFromColumnIndex($c);
            $to = Coordinate::stringFromColumnIndex($c + 1);
            $this->text($sheet, "{$from}5", $kpi[0]);
            $sheet->setCellValue("{$from}6", $kpi[1]);
            $sheet->mergeCells("{$from}5:{$to}5");
            $sheet->mergeCells("{$from}6:{$to}6");
            $sheet->getStyle("{$from}5:{$to}6")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $kpi[3]]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => $kpi[2]]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("{$from}5")->getFont()->setSize(9)->getColor()->setRGB(self::MUTED);
            $sheet->getStyle("{$from}6")->getFont()->setSize(15)->setBold(true)->getColor()->setRGB($kpi[2]);
            $sheet->getStyle("{$from}6")->getNumberFormat()->setFormatCode($kpi[4]);
            $c += 2;
        }
        $sheet->getRowDimension(5)->setRowHeight(20);
        $sheet->getRowDimension(6)->setRowHeight(30);

        // ─── فلتر وتجميد وطباعة ───
        $sheet->setAutoFilter("A{$head}:{$last}{$end}");
        $sheet->freezePane('A'.$first);
        $sheet->setSelectedCell('A'.$first);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($head, $head)
            ->setPrintArea("A1:{$last}{$totalRow}");
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.$this->footerText($this->meta['business'] ?? '').'&R&8&P / &N');

        return $book;
    }

    private function footerText($text): string
    {
        // & ليها معنى خاص في تذييل Excel
        return str_replace('&', '&&', (string) $text);
    }

    private function text(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    public function xlsxResponse(string $name)
    {
        $book = $this->spreadsheet();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function csvRows(): array
    {
        $columns = array_values(array_filter($this->columns(), fn ($c) => $c['key'] !== 'n'));
        $lines = [array_column($columns, 'label')];
        foreach ($this->records() as $record) {
            $line = [];
            foreach ($columns as $column) {
                $value = $record['values'][$column['key']];
                if (($column['type'] ?? '') === 'date') {
                    $value = Carbon::parse($value)->format('Y-m-d H:i');
                } elseif (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                    // CSV injection: Excel بيشغّل الخلايا اللي بتبدأ بالرموز دي كمعادلات
                    $value = "'".$value;
                }
                $line[] = $value;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    public function csvResponse(string $name)
    {
        $lines = $this->csvRows();

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($lines as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
