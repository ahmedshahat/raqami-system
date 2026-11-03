<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * تقرير الموردين أو العملاء بكل الفلاتر (مش الصفحة الظاهرة بس): صفوفه للطباعة، وملف Excel منه.
 * المبالغ بنفس معادلات أعمدة الجدول في ContactController.
 * كل النصوص بتتكتب كنص صريح، عشان اسم زي "=HYPERLINK(...)" ما يتحولش لمعادلة.
 */
class ContactsListExport
{
    // ألوان رقمي (public/css/raqmi.css) من غير #
    private const BRAND = '22177A';
    private const BRAND_TINT = 'ECEAFB';
    private const INK = '1B1F2A';
    private const MUTED = '5B6475';
    private const LINE = 'DDE1E8';
    private const SUBTLE = '586174';
    private const ROW_TINT = 'F6F5FD';
    private const WARNING = '7C4A00';
    private const WARNING_TINT = 'FFF1D3';

    private const HEAD_ROW = 5;

    public function __construct(private Collection $rows, private array $meta)
    {
    }

    public function isCustomer(): bool
    {
        return ($this->meta['type'] ?? '') === 'customer';
    }

    public function records(): array
    {
        $records = [];
        foreach ($this->rows->values() as $i => $row) {
            if ($this->isCustomer()) {
                $due = (float) $row->total_invoice - (float) $row->invoice_received - (float) $row->total_ledger_discount - (float) $row->total_sell_return + (float) $row->sell_return_paid;
                $return_due = (float) $row->total_sell_return - (float) $row->sell_return_paid;
            } else {
                $return_due = (float) $row->total_purchase_return - (float) $row->purchase_return_paid;
                $due = (float) $row->total_purchase - (float) $row->purchase_paid - (float) $row->total_ledger_discount - $return_due;
            }

            // نفس عمود المستحق في الجدول: شامل المتبقي من الرصيد الافتتاحي. نوع "عميل ومورد" رصيده بيتحسب مع العملاء
            $opening_left = ! $this->isCustomer() && $row->type === 'both' ? 0 : (float) $row->opening_balance - (float) $row->opening_balance_paid;

            $records[] = [
                'inactive' => $row->contact_status !== 'active',
                'values' => [
                    'n' => $i + 1,
                    'contact_id' => (string) $row->contact_id,
                    'business_name' => (string) $row->supplier_business_name,
                    'name' => trim((string) $row->name),
                    'mobile' => (string) $row->mobile,
                    'email' => (string) $row->email,
                    'tax_number' => (string) $row->tax_number,
                    'group' => (string) $row->customer_group,
                    'credit_limit' => is_null($row->credit_limit) ? __('lang_v1.no_limit') : (string) round((float) $row->credit_limit, 4),
                    'pay_term' => ! empty($row->pay_term_type) && ! empty($row->pay_term_number) ? $row->pay_term_number.' '.__('lang_v1.'.$row->pay_term_type) : '',
                    'due' => $due + $opening_left,
                    'return_due' => $return_due,
                    'opening' => (float) $row->opening_balance,
                    'opening_left' => max($opening_left, 0),
                    'advance' => (float) $row->balance,
                    'status' => $row->contact_status === 'active' ? __('contact_list.active') : __('contact_list.inactive'),
                    'address' => implode('، ', array_filter([$row->address_line_1, $row->address_line_2, $row->city, $row->state, $row->country, $row->zip_code])),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d') : '',
                ],
            ];
        }

        return $records;
    }

    /**
     * الأعمدة الاختيارية في تقرير الطباعة: key => [العنوان، ظاهر افتراضيا، مبلغ؟]. المبالغ المستحقة ثابتة في التقرير.
     */
    public static function reportColumnOptions(string $type): array
    {
        $options = [
            'mobile' => [__('contact_list.col_mobile'), true, false],
            'email' => [__('contact_list.col_email'), false, false],
            'tax_number' => [__('contact_list.col_tax'), false, false],
        ];
        if ($type === 'customer') {
            $options['group'] = [__('contact_list.col_group'), false, false];
            $options['credit_limit'] = [__('contact_list.col_credit_limit'), false, false];
        }

        return $options + [
            'pay_term' => [__('contact_list.col_pay_term'), false, false],
            'address' => [__('contact_list.col_address'), false, false],
            'created_at' => [__('contact_list.col_added_on'), false, false],
            'return_due' => [__('contact_list.col_return_due'), false, true],
            'advance' => [__('contact_list.col_advance'), true, true],
        ];
    }

    /**
     * صفوف تقرير الطباعة. الإجمالي هو عمود المستحق في الجدول، والسالب بيتحسب صفر زي مؤشرات الشاشة،
     * فإجمالي العمود بيطابق مؤشره في listSummary. مستحق الفواتير هو الباقي بعد الرصيد الافتتاحي عشان الأعمدة تتجمع صح.
     */
    public function reportRecords(bool $onlyDue = false): array
    {
        $records = [];
        foreach ($this->records() as $record) {
            $record['values']['return_due'] = max($record['values']['return_due'], 0);
            $record['values']['total_due'] = max($record['values']['due'], 0);
            $record['values']['invoice_due'] = $record['values']['total_due'] - $record['values']['opening_left'];
            if (! $onlyDue || $record['values']['total_due'] > 0) {
                $records[] = $record;
            }
        }

        if ($onlyDue) {
            usort($records, fn ($a, $b) => $b['values']['total_due'] <=> $a['values']['total_due']);
        }
        foreach ($records as $i => &$record) {
            $record['values']['n'] = $i + 1;
        }

        return $records;
    }

    /**
     * نسخة Excel من تقرير الطباعة (contact/print_list): نفس الصفوف والأعمدة والإجماليات.
     * الاسم والكود في عمودين منفصلين عشان فلتر Excel، والإجماليات SUBTOTAL فبتتبع الفلتر.
     */
    public function reportSpreadsheet(array $records, array $columns, bool $onlyDue): Spreadsheet
    {
        $type = $this->meta['type'];
        $title = __('contact_list.report_title_'.($onlyDue ? 'due_' : '').$type);
        $widths = ['mobile' => 16, 'email' => 24, 'tax_number' => 18, 'group' => 16, 'credit_limit' => 14, 'pay_term' => 14, 'address' => 38, 'created_at' => 14];

        $table = [
            ['key' => 'n', 'label' => '#', 'width' => 6, 'kind' => 'int'],
            ['key' => 'contact', 'label' => __('contact_list.col_contact'), 'width' => 34, 'kind' => 'text'],
            ['key' => 'contact_id', 'label' => __('contact_list.col_code'), 'width' => 14, 'kind' => 'text'],
        ];
        foreach ($columns as $key => $column) {
            if (! $column[2]) {
                $table[] = ['key' => $key, 'label' => $column[0], 'width' => $widths[$key] ?? 16, 'kind' => 'text'];
            }
        }
        $table[] = ['key' => 'invoice_due', 'label' => __('contact_list.report_col_invoice_due'), 'width' => 16, 'kind' => 'money'];
        $table[] = ['key' => 'opening_left', 'label' => __('contact_list.report_col_opening_left'), 'width' => 20, 'kind' => 'money'];
        foreach ($columns as $key => $column) {
            if ($column[2]) {
                $table[] = ['key' => $key, 'label' => $column[0], 'width' => 16, 'kind' => 'money'];
            }
        }
        $table[] = ['key' => 'total_due', 'label' => __('contact_list.report_col_total'), 'width' => 18, 'kind' => 'money'];

        $letters = [];
        foreach ($table as $i => $column) {
            $letters[$column['key']] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $last = end($letters);

        $book = new Spreadsheet();
        $book->getProperties()->setTitle($title)->setCreator((string) ($this->meta['business'] ?? ''));
        $book->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10)->getColor()->setRGB(self::INK);

        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->setRightToLeft(app()->getLocale() === 'ar');
        $sheet->setShowGridlines(false);

        // ─── الرأس ───
        $this->text($sheet, 'A1', (string) ($this->meta['business'] ?? ''));
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true)->getColor()->setRGB(self::MUTED);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $this->text($sheet, 'A2', $title);
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setSize(18)->setBold(true)->getColor()->setRGB(self::BRAND);
        $sheet->getRowDimension(2)->setRowHeight(32);

        $note = trim((string) ($this->meta['note'] ?? ''));
        $this->text($sheet, 'A3', __('contact_list.report_count_'.$type).': '.count($records).'  ·  '.
            ($note !== '' ? __('contact_list.active_filters').': '.$note.'  ·  ' : '').
            __('contact_list.xl_generated', ['date' => now()->format('Y-m-d H:i'), 'user' => (string) ($this->meta['user'] ?? '')]));
        $sheet->mergeCells("A3:{$last}3");
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB(self::MUTED);

        $logo = ! empty($this->meta['logo']) ? public_path('uploads/business_logos/'.$this->meta['logo']) : null;
        if ($logo && is_file($logo) && @getimagesize($logo)) {
            $drawing = new Drawing();
            $drawing->setPath($logo)->setHeight(44)->setCoordinates($last.'1')->setWorksheet($sheet);
        }

        // ─── الجدول ───
        $head = self::HEAD_ROW;
        $first = $head + 1;
        $end = max($first, $head + count($records));
        $totalRow = $end + 1;

        foreach ($table as $column) {
            $letter = $letters[$column['key']];
            $this->text($sheet, $letter.$head, $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }
        $sheet->getStyle("A{$head}:{$last}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($head)->setRowHeight(28);

        $money = $this->moneyFormat();
        $r = $first;
        foreach ($records as $i => $record) {
            $v = $record['values'];
            $v['contact'] = implode(' · ', array_filter([$v['business_name'], $v['name'], $record['inactive'] ? $v['status'] : '']));
            foreach ($table as $column) {
                $cell = $letters[$column['key']].$r;
                $value = $v[$column['key']];
                if ($column['kind'] === 'money') {
                    $sheet->setCellValue($cell, $value);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($money);
                    if ($value == 0) {
                        $sheet->getStyle($cell)->getFont()->getColor()->setRGB(self::SUBTLE);
                    }
                } elseif ($column['kind'] === 'int') {
                    $sheet->setCellValue($cell, $value);
                } elseif ($column['key'] === 'credit_limit' && is_numeric($value)) {
                    $sheet->setCellValue($cell, (float) $value);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($money);
                } else {
                    $this->text($sheet, $cell, (string) $value);
                }
            }
            if ($i % 2) {
                $sheet->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROW_TINT);
            }
            if ($v['total_due'] > 0) {
                $sheet->getStyle($letters['total_due'].$r)->getFont()->setBold(true)->getColor()->setRGB(self::WARNING);
            }
            $r++;
        }
        $sheet->getStyle("A{$first}:{$last}{$end}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        if (isset($letters['address'])) {
            $sheet->getStyle("{$letters['address']}{$first}:{$letters['address']}{$end}")->getAlignment()->setWrapText(true);
        }

        if (empty($records)) {
            $this->text($sheet, "A{$first}", __('contact_list.empty'));
            $sheet->mergeCells("A{$first}:{$last}{$first}");
            $sheet->getStyle("A{$first}")->getFont()->getColor()->setRGB(self::MUTED);
        } else {
            $sheet->getStyle("A{$first}:{$last}{$end}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
            $sheet->getStyle("A{$first}:{$last}{$end}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);

            // ─── صف الإجمالي ───
            $this->text($sheet, "A{$totalRow}", __('contact_list.xl_total_row'));
            foreach ($table as $column) {
                if ($column['kind'] === 'money') {
                    $letter = $letters[$column['key']];
                    $sheet->setCellValue($letter.$totalRow, "=SUBTOTAL(109,{$letter}{$first}:{$letter}{$end})");
                    $sheet->getStyle($letter.$totalRow)->getNumberFormat()->setFormatCode($money);
                }
            }
            $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::BRAND]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_TINT]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRAND]]],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);

            // ─── ملخص المستحقات تحت الجدول، في آخر الأعمدة زي الطباعة ───
            $labelFrom = Coordinate::stringFromColumnIndex(count($table) - 2);
            $labelTo = Coordinate::stringFromColumnIndex(count($table) - 1);
            $lines = [
                [__('contact_list.report_count_owing_'.$type), "=COUNTIF({$letters['total_due']}{$first}:{$letters['total_due']}{$end},\">0\")", '#,##0'],
                [__('contact_list.report_col_invoice_due'), "={$letters['invoice_due']}{$totalRow}", $money],
                [__('contact_list.report_col_opening_left'), "={$letters['opening_left']}{$totalRow}", $money],
            ];
            $s = $totalRow + 2;
            foreach ($lines as $line) {
                $this->text($sheet, $labelFrom.$s, $line[0]);
                $sheet->mergeCells("{$labelFrom}{$s}:{$labelTo}{$s}");
                $sheet->getStyle($labelFrom.$s)->getFont()->getColor()->setRGB(self::MUTED);
                $sheet->setCellValue($last.$s, $line[1]);
                $sheet->getStyle($last.$s)->getNumberFormat()->setFormatCode($line[2]);
                $sheet->getStyle($last.$s)->getFont()->setBold(true);
                $s++;
            }

            $this->text($sheet, $labelFrom.$s, __('contact_list.report_total_due_'.$type));
            $sheet->mergeCells("{$labelFrom}{$s}:{$labelTo}{$s}");
            $sheet->setCellValue($last.$s, "={$letters['total_due']}{$totalRow}");
            $symbol = str_replace('"', '', (string) ($this->meta['symbol'] ?? ''));
            $sheet->getStyle($last.$s)->getNumberFormat()->setFormatCode($money.($symbol !== '' ? ' "'.$symbol.'"' : ''));
            $sheet->getStyle("{$labelFrom}{$s}:{$last}{$s}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::WARNING]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::WARNING_TINT]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getColumnDimension($last)->setWidth(22);
            $sheet->getRowDimension($s)->setRowHeight(30);
            $printEnd = $s;
        }

        // ─── فلتر وتجميد وطباعة ───
        $sheet->setAutoFilter("A{$head}:{$last}{$end}");
        $sheet->freezePane('A'.$first);
        $sheet->setSelectedCell('A'.$first);
        $sheet->getPageSetup()
            ->setOrientation(count($columns) > 2 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($head, $head)
            ->setPrintArea('A1:'.$last.($printEnd ?? $end));

        return $book;
    }

    private function moneyFormat(): string
    {
        $precision = max(0, min(4, (int) ($this->meta['precision'] ?? 2)));

        return '#,##0'.($precision ? '.'.str_repeat('0', $precision) : '');
    }

    private function text(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    public function xlsxResponse(string $name, Spreadsheet $book)
    {
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
