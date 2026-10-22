<?php

namespace Tests\Unit;

use Modules\Construction\Exports\BoqWorkbookExport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ConstructionExportFormulaInjectionTest extends TestCase
{
    public function test_text_starting_with_equals_is_written_as_plain_text(): void
    {
        $export = new BoqWorkbookExport(collect());
        $sheet = (new Spreadsheet())->getActiveSheet();

        $formulaCell = $sheet->getCell('A1');
        $export->bindValue($formulaCell, '=HYPERLINK("http://example.com","click")');
        $this->assertSame(DataType::TYPE_STRING, $formulaCell->getDataType());

        $codeCell = $sheet->getCell('A2');
        $export->bindValue($codeCell, '01.001');
        $this->assertSame(DataType::TYPE_STRING, $codeCell->getDataType());
        $this->assertSame('01.001', $codeCell->getValue());

        $numberCell = $sheet->getCell('A3');
        $export->bindValue($numberCell, 2500.5);
        $this->assertSame(DataType::TYPE_NUMERIC, $numberCell->getDataType());
    }
}
