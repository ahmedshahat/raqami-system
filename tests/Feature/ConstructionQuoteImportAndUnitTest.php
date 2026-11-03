<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionQuoteImportBatch;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ConstructionQuoteImportAndUnitTest extends TestCase
{
    use DatabaseTransactions;

    private Business $business;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $this->unit = Unit::where('business_id', $this->business->id)->firstOrFail();
        $this->actingAs(User::findOrFail($this->business->owner_id));
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function test_new_template_import_generates_codes_on_commit_in_row_order(): void
    {
        $quote = $this->quoteWithItems(['ITM-001', 'ITM-003']);
        $file = $this->workbook([
            ['بيان الأعمال', 'الوحدة', 'الكمية', 'سعر الوحدة', 'ملاحظات'],
            ['حفر', $this->unit->short_name, 10, 50, 'أول'],
            ['ردم', $this->unit->short_name, 4, 25, ''],
        ]);

        $batch = $this->preview($quote, $file);
        foreach ($batch->rows_json as $row) {
            $this->assertArrayNotHasKey('code', $row);
            $this->assertSame([], $row['errors']);
        }

        $this->post(route('construction.quotes.items.import.commit', [$quote->id, $batch->token]))->assertRedirect();

        $imported = $quote->items()->whereNotIn('code', ['ITM-001', 'ITM-003'])->orderBy('sort_order')->get();
        $this->assertSame(['ITM-004', 'ITM-005'], $imported->pluck('code')->all());
        $this->assertSame(['حفر', 'ردم'], $imported->pluck('description')->all());
        $this->assertEquals(500, (float) $imported[0]->total);
        $this->assertEquals(100, (float) $imported[1]->total);
    }

    public function test_old_template_code_and_total_columns_are_ignored(): void
    {
        $quote = $this->quoteWithItems([]);
        $file = $this->workbook([
            ['كود البند', 'بيان الأعمال', 'الوحدة', 'الكمية', 'سعر الوحدة', 'ملاحظات', 'إجمالي البند'],
            ['CUSTOM-9', 'خرسانة', $this->unit->short_name, 3, 200, 'قديم', 999999],
        ]);

        $batch = $this->preview($quote, $file);
        $this->assertSame([], $batch->rows_json[0]['errors']);
        $this->assertEquals(600, $batch->rows_json[0]['total']);

        $this->post(route('construction.quotes.items.import.commit', [$quote->id, $batch->token]))->assertRedirect();

        $item = $quote->items()->sole();
        $this->assertSame('ITM-001', $item->code);
        $this->assertSame('خرسانة', $item->description);
        $this->assertSame('قديم', $item->notes);
        $this->assertEquals(600, (float) $item->total);
    }

    public function test_unknown_unit_is_reported_by_name(): void
    {
        $quote = $this->quoteWithItems([]);
        $file = $this->workbook([
            ['بيان الأعمال', 'الوحدة', 'الكمية', 'سعر الوحدة', 'ملاحظات'],
            ['دهانات', 'وحدة-غير-موجودة-'.uniqid(), 1, 10, ''],
        ]);

        $batch = $this->preview($quote, $file);
        $this->assertSame(1, $batch->error_count);
        $this->assertStringContainsString('وحدة-غير-موجودة', $batch->rows_json[0]['errors'][0]);
    }

    public function test_quick_unit_create_saves_business_unit_and_rejects_duplicates(): void
    {
        $name = 'متر طولي '.uniqid();
        $short = 'مط'.substr(uniqid(), -4);

        $response = $this->postJson(route('construction.units.store'), [
            'actual_name' => $name, 'short_name' => $short, 'allow_decimal' => 1,
        ])->assertCreated();

        $unit = Unit::findOrFail($response->json('id'));
        $this->assertSame($this->business->id, (int) $unit->business_id);
        $this->assertSame(1, (int) $unit->allow_decimal);
        $this->assertSame($name.' ('.$short.')', $response->json('label'));

        $this->postJson(route('construction.units.store'), ['actual_name' => $name, 'short_name' => 'x'.uniqid()])
            ->assertStatus(422)->assertJsonValidationErrors('actual_name');
        $this->postJson(route('construction.units.store'), ['actual_name' => 'y'.uniqid(), 'short_name' => $short])
            ->assertStatus(422)->assertJsonValidationErrors('short_name');
    }

    public function test_quote_print_shows_vat_exclusive_note_in_both_languages(): void
    {
        $quote = $this->quoteWithItems(['ITM-001']);

        app()->setLocale('ar');
        $this->get(route('construction.quotes.preview', $quote->id))->assertOk()
            ->assertSee('الأسعار لا تشمل ضريبة القيمة المضافة');
        // المعاينة والطباعة والـ PDF بيرسموا نفس القالب (quotes.print)
        $this->get(route('construction.quotes.print', $quote->id))->assertOk()
            ->assertSee('الأسعار لا تشمل ضريبة القيمة المضافة');
        $pdfHtml = view('construction::quotes.print', [
            'quote' => $quote->load('items', 'customer'), 'business' => $this->business,
            'businessLocation' => $this->business->locations()->first(),
            'pdfMode' => true, 'autoPrint' => false,
        ])->render();
        $this->assertStringContainsString('الأسعار لا تشمل ضريبة القيمة المضافة', $pdfHtml);

        app()->setLocale('en');
        $this->assertStringContainsString('Prices are exclusive of VAT', __('construction::lang.quote_prices_exclude_vat'));
    }

    private function quoteWithItems(array $codes): ConstructionQuote
    {
        $customer = Contact::where('business_id', $this->business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $quote = ConstructionQuote::create([
            'business_id' => $this->business->id, 'number' => 'QTN-IMP-'.uniqid(), 'quote_date' => '2026-10-22',
            'customer_id' => $customer->id, 'title' => 'Import test', 'validity_days' => 30,
            'status' => 'draft', 'created_by' => $this->business->owner_id,
        ]);
        foreach ($codes as $index => $code) {
            $quote->items()->create([
                'business_id' => $this->business->id, 'code' => $code, 'description' => 'Existing '.$code,
                'unit_id' => $this->unit->id, 'unit' => $this->unit->short_name,
                'quantity' => 1, 'unit_price' => 1, 'total' => 1, 'sort_order' => ($index + 1) * 10,
            ]);
        }

        return $quote;
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'qimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function preview(ConstructionQuote $quote, UploadedFile $file): ConstructionQuoteImportBatch
    {
        $this->post(route('construction.quotes.items.import.preview', $quote->id), ['quote_file' => $file])->assertRedirect();

        return ConstructionQuoteImportBatch::where('quote_id', $quote->id)->latest('id')->firstOrFail();
    }
}
