<?php

namespace Tests\Unit;

use App\Business;
use App\Unit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Construction\Exports\BoqWorkbookExport;
use Modules\Construction\Http\Controllers\BoqSpreadsheetController;
use ReflectionMethod;
use Tests\TestCase;

class ConstructionBoqSpreadsheetTest extends TestCase
{
    use DatabaseTransactions;
    public function test_boq_template_exports_as_a_valid_xlsx_with_expected_columns(): void
    {
        session([
            'business.quantity_precision' => 3,
            'business.currency_precision' => 1,
        ]);
        $export = BoqWorkbookExport::template();
        $rows = $export->array();

        $this->assertSame('نوع السطر', $rows[0][0]);
        $this->assertSame('كود البند', $rows[0][1]);
        $this->assertSame('إجمالي البند', $rows[0][9]);
        $this->assertSame(250000.0, $rows[2][9]);
        $this->assertSame('#,##0.000', $export->columnFormats()['F']);
        $this->assertSame('#,##0.0', $export->columnFormats()['G']);
        $this->assertSame('#,##0.0', $export->columnFormats()['J']);

        $binary = Excel::raw($export, ExcelFormat::XLSX);
        $this->assertStringStartsWith('PK', $binary);
        $this->assertGreaterThan(5000, strlen($binary));
    }

    public function test_import_preview_normalizes_valid_rows_and_calculates_totals(): void
    {
        $business = Business::whereHas('locations')->firstOrFail();
        session(['user.business_id' => $business->id]);
        $unit = Unit::create(['business_id' => $business->id, 'actual_name' => 'متر مكعب', 'short_name' => 'م3', 'allow_decimal' => 1, 'created_by' => $business->owner_id]);
        $normalizer = new ReflectionMethod(BoqSpreadsheetController::class, 'normalizeRows');
        $rows = $normalizer->invoke(new BoqSpreadsheetController(), [
            ['باب', '01', 'الأعمال الخرسانية', '', '', 0, 0, 'أساسي', '', null],
            ['بند', '01.001', 'خرسانة مسلحة', '01', 'م3', 100, 2500, 'أساسي', '', null],
        ], collect());

        $this->assertCount(2, $rows);
        $this->assertSame([], $rows[0]['errors']);
        $this->assertSame([], $rows[1]['errors']);
        $this->assertSame($unit->id, $rows[1]['unit_id']);
        $this->assertSame(250000.0, $rows[1]['sales_total']);

        $unknown = $normalizer->invoke(new BoqSpreadsheetController(), [
            ['بند', '02.001', 'بند بوحدة غير مسجلة', '', 'UNKNOWN-UNIT', 1, 10, 'أساسي', '', null],
        ], collect());
        $this->assertContains(__('construction::lang.import_unit_not_found'), $unknown[0]['errors']);
    }
}
