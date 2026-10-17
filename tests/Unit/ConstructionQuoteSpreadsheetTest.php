<?php

namespace Tests\Unit;

use App\Business;
use App\Contact;
use App\Currency;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionQuoteImportBatch;
use Modules\Construction\Exports\QuoteItemsWorkbookExport;
use Modules\Construction\Http\Controllers\QuoteSpreadsheetController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ReflectionMethod;
use Tests\TestCase;

class ConstructionQuoteSpreadsheetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quote_items_template_exports_a_valid_xlsx(): void
    {
        session([
            'business.quantity_precision' => 3,
            'business.currency_precision' => 2,
        ]);
        $export = QuoteItemsWorkbookExport::template();
        $rows = $export->array();

        $this->assertSame('كود البند', $rows[0][0]);
        $this->assertSame('بيان الأعمال', $rows[0][1]);
        $this->assertSame('إجمالي البند', $rows[0][6]);
        $this->assertSame(250000.0, $rows[1][6]);
        $this->assertSame('#,##0.000', $export->columnFormats()['D']);
        $this->assertSame('#,##0.00', $export->columnFormats()['E']);

        $binary = Excel::raw($export, ExcelFormat::XLSX);
        $this->assertStringStartsWith('PK', $binary);
        $this->assertGreaterThan(5000, strlen($binary));
    }

    public function test_quote_import_normalizes_rows_matches_units_and_reports_errors(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        session(['user.business_id' => $business->id]);
        $unit = Unit::where('business_id', $business->id)->whereNull('deleted_at')->firstOrFail();
        $normalizer = new ReflectionMethod(QuoteSpreadsheetController::class, 'normalizeRows');
        $rows = $normalizer->invoke(new QuoteSpreadsheetController(), [[
            '', 'أعمال خرسانة مسلحة', $unit->short_name, 10, 2500, 'بند تجريبي', null,
        ]], collect(), 1, 10);

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['errors']);
        $this->assertSame('ITM-001', $rows[0]['code']);
        $this->assertSame($unit->id, $rows[0]['unit_id']);
        $this->assertSame(25000.0, $rows[0]['total']);

        $invalid = $normalizer->invoke(new QuoteSpreadsheetController(), [[
            'EXISTING', '', 'UNKNOWN-UNIT', 0, -1, '', null,
        ]], collect(['existing' => true]), 2, 20);

        $this->assertContains(__('construction::lang.quote_import_duplicate_code'), $invalid[0]['errors']);
        $this->assertContains(__('construction::lang.import_description_required'), $invalid[0]['errors']);
        $this->assertContains(__('construction::lang.import_unit_not_found'), $invalid[0]['errors']);
        $this->assertContains(__('construction::lang.quote_import_invalid_quantity'), $invalid[0]['errors']);
        $this->assertContains(__('construction::lang.import_invalid_price'), $invalid[0]['errors']);
    }

    public function test_valid_workbook_can_be_previewed_and_committed_to_a_quote(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $business->update(['currency_id' => Currency::where('code', 'SAR')->firstOrFail()->id]);
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::where('business_id', $business->id)->whereNull('deleted_at')->firstOrFail();
        $this->actingAs($user);
        session(['user.business_id' => $business->id, 'business' => $business->fresh()]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $quote = ConstructionQuote::create([
            'business_id' => $business->id,
            'number' => 'QTN-IMPORT-'.uniqid(),
            'quote_date' => '2026-10-14',
            'customer_id' => $customer->id,
            'title' => 'اختبار استيراد البنود',
            'validity_days' => 30,
            'status' => 'draft',
            'total' => 0,
            'created_by' => $user->id,
        ]);
        $export = new QuoteItemsWorkbookExport(collect([(object) [
            'code' => 'XL-001', 'description' => 'بند مستورد', 'unit' => $unit->short_name,
            'quantity' => 4, 'unit_price' => 1250, 'notes' => 'من ملف الاختبار', 'total' => 5000,
        ]]));
        $file = UploadedFile::fake()->createWithContent('quote-items.xlsx', Excel::raw($export, ExcelFormat::XLSX));
        $uploadedRows = IOFactory::load($file->getRealPath())->getActiveSheet()->rangeToArray('A2:G2', null, true, false, false);
        $this->assertEquals(1250, $uploadedRows[0][4]);

        $response = $this->post(route('construction.quotes.items.import.preview', $quote->id), ['quote_file' => $file]);
        $batch = ConstructionQuoteImportBatch::where('quote_id', $quote->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('construction.quotes.items.import.review', [$quote->id, $batch->token]));
        $this->assertSame(0, $batch->error_count, json_encode($batch->rows_json, JSON_UNESCAPED_UNICODE));
        $this->get(route('construction.quotes.items.import.review', [$quote->id, $batch->token]))
            ->assertOk()->assertSee('XL-001')->assertSee(__('construction::lang.confirm_import'));

        $this->post(route('construction.quotes.items.import.commit', [$quote->id, $batch->token]))
            ->assertRedirect(route('construction.quotes.show', $quote->id));
        $this->assertSame(1, $quote->items()->count());
        $this->assertSame('XL-001', $quote->items()->first()->code);
        $this->assertEquals(5000, (float) $quote->fresh()->total);
        $this->assertSame('imported', $batch->fresh()->status);
    }
}
