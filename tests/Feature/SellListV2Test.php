<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SellListV2Test extends TestCase
{
    use DatabaseTransactions;

    private $business;

    private $user;

    // فترة قديمة مفيهاش بيانات حقيقية، عشان الملخص يحسب فواتير الاختبار بس
    private $date = '2001-03-10 10:00:00';

    // نفس الأعمدة اللي جدول /sells/v2 بيبعتها (مع البحث والترتيب بالتاريخ)
    private $columns = [
        ['data' => 'transaction_id', 'name' => 'transactions.id', 'searchable' => 'false', 'orderable' => 'false'],
        ['data' => 'invoice_no', 'name' => 'transactions.invoice_no', 'searchable' => 'true', 'orderable' => 'false'],
        ['data' => 'conatct_name', 'name' => 'conatct_name', 'searchable' => 'true', 'orderable' => 'false'],
        ['data' => 'business_location', 'name' => 'bl.name', 'searchable' => 'false', 'orderable' => 'true'],
        ['data' => 'final_total', 'name' => 'final_total', 'searchable' => 'false', 'orderable' => 'true'],
        ['data' => 'transaction_date', 'name' => 'transaction_date', 'searchable' => 'false', 'orderable' => 'true'],
        ['data' => 'mobile', 'name' => 'contacts.mobile', 'searchable' => 'true', 'orderable' => 'false'],
    ];

    private $range = ['start_date' => '2001-03-01', 'end_date' => '2001-03-31', 'is_direct_sale' => 1];

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($this->business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    private function makeSell($total, $paid, $status, $extra = [])
    {
        $contact = Contact::where('business_id', $this->business->id)
            ->whereIn('type', ['customer', 'both'])
            ->firstOrFail();

        $sell = Transaction::create(array_merge([
            'business_id' => $this->business->id,
            'location_id' => $this->business->locations()->firstOrFail()->id,
            'type' => 'sell',
            'status' => 'final',
            'is_direct_sale' => 1,
            'payment_status' => $status,
            'contact_id' => $contact->id,
            'invoice_no' => 'V2-TEST-'.uniqid(),
            'transaction_date' => $this->date,
            'total_before_tax' => $total,
            'final_total' => $total,
            'created_by' => $this->user->id,
        ], $extra));

        if ($paid > 0) {
            TransactionPayment::create([
                'transaction_id' => $sell->id,
                'business_id' => $this->business->id,
                'amount' => $paid,
                'method' => 'cash',
                'paid_on' => $this->date,
                'created_by' => $this->user->id,
                'payment_for' => $contact->id,
            ]);
        }

        return $sell;
    }

    private function summary($params = [])
    {
        return $this->getJson(route('sells.v2.summary', array_merge($this->range, $params)))->assertOk();
    }

    public function test_summary_totals_cover_the_whole_filtered_period(): void
    {
        $paid = $this->makeSell(100, 100, 'paid');
        $this->makeSell(200, 50, 'partial');
        $this->makeSell(300, 0, 'due', ['pay_term_number' => 5, 'pay_term_type' => 'days']);

        Transaction::create([
            'business_id' => $this->business->id,
            'location_id' => $paid->location_id,
            'type' => 'sell_return',
            'status' => 'final',
            'payment_status' => 'paid',
            'contact_id' => $paid->contact_id,
            'return_parent_id' => $paid->id,
            'invoice_no' => 'V2-RET-'.uniqid(),
            'transaction_date' => $this->date,
            'total_before_tax' => 40,
            'final_total' => 40,
            'created_by' => $this->user->id,
        ]);

        $data = $this->summary()->json();

        $this->assertEquals(600, $data['total']);
        $this->assertEquals(150, $data['paid']);
        $this->assertEquals(450, $data['due']);
        $this->assertEquals(40, $data['returns']);
        $this->assertSame(3, $data['invoices']);
        $this->assertEquals(200, $data['average']);
        $this->assertSame(['all' => 3, 'paid' => 1, 'partial' => 1, 'due' => 1, 'overdue' => 1], $data['counts']);
    }

    public function test_summary_ignores_the_payment_status_tab_but_applies_search(): void
    {
        $paid = $this->makeSell(100, 100, 'paid');
        $this->makeSell(300, 0, 'due');

        $this->assertSame(2, $this->summary(['payment_status' => 'paid'])->json('invoices'));

        $data = $this->summary(['search' => $paid->invoice_no])->json();
        $this->assertSame(1, $data['invoices']);
        $this->assertEquals(100, $data['total']);
    }

    public function test_unpaid_filter_lists_due_and_partial_invoices(): void
    {
        $this->makeSell(100, 100, 'paid');
        $partial = $this->makeSell(200, 50, 'partial');
        $due = $this->makeSell(300, 0, 'due');

        $rows = $this->getJson('/sells?'.http_build_query($this->range + [
            'payment_status' => 'unpaid',
            'draw' => 1, 'start' => 0, 'length' => 25,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');

        $ids = collect($rows)->pluck('transaction_id')->sort()->values()->all();
        $this->assertSame(collect([$partial->id, $due->id])->sort()->values()->all(), $ids);
    }

    public function test_list_endpoint_accepts_the_new_table_columns_and_search(): void
    {
        $sell = $this->makeSell(100, 100, 'paid');
        $this->makeSell(300, 0, 'due');

        $rows = $this->getJson('/sells?'.http_build_query($this->range + [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'columns' => $this->columns,
            'order' => [['column' => 5, 'dir' => 'desc']],
            'search' => ['value' => $sell->invoice_no],
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');

        $this->assertSame([$sell->id], collect($rows)->pluck('transaction_id')->all());
        $this->assertStringContainsString('dropdown-menu', $rows[0]['action']);
        $this->assertSame($sell->invoice_no, $rows[0]['invoice_no_text']);
    }

    public function test_list_count_follows_new_invoices_and_search(): void
    {
        $list = function ($search = '') {
            // yajra بيحتفظ بالطلب كـ singleton، وكذا طلب في نفس الاختبار محتاجين نسخة جديدة
            app()->forgetInstance('datatables.request');

            return $this->getJson('/sells?'.http_build_query($this->range + [
                'draw' => 1, 'start' => 0, 'length' => 25,
                'columns' => $this->columns,
                'order' => [['column' => 5, 'dir' => 'desc']],
                'search' => ['value' => $search],
            ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
        };

        $first = $this->makeSell(100, 100, 'paid');
        $this->makeSell(200, 0, 'due');
        $this->assertSame(2, $list()['recordsFiltered']);

        // فاتورة جديدة لازم تتعد فورًا، مش بعد ما الكاش يخلص
        $this->makeSell(300, 0, 'due');
        $this->assertSame(3, $list()['recordsFiltered']);

        $data = $list($first->invoice_no);
        $this->assertCount(1, $data['data']);
        $this->assertSame(1, $data['recordsFiltered']);
    }

    private function exportBook($params = [])
    {
        $response = $this->get(route('sells.v2.export', array_merge($this->range, $params)))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'sx').'.xlsx';
        file_put_contents($file, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        @unlink($file);

        return $sheet;
    }

    public function test_excel_export_covers_the_whole_period_not_one_page(): void
    {
        // أكتر من صفحة (25) عشان نتأكد إن التصدير مش للصفحة الظاهرة بس
        $sells = collect();
        for ($i = 0; $i < 27; $i++) {
            $sells->push($this->makeSell(10, 10, 'paid'));
        }
        $due = $this->makeSell(300, 50, 'partial', ['contact_id' => Contact::where('business_id', $this->business->id)->firstOrFail()->id]);

        $sheet = $this->exportBook();
        $head = 8;
        $last = $head + 28;

        $this->assertSame('#', $sheet->getCell('A'.$head)->getValue());
        $this->assertSame(__('sell_list.xl_total_row'), $sheet->getCell('A'.($last + 1))->getValue());
        $invoices = [];
        for ($r = $head + 1; $r <= $last; $r++) {
            $invoices[] = $sheet->getCell('B'.$r)->getValue();
        }
        $this->assertEqualsCanonicalizing($sells->pluck('invoice_no')->push($due->invoice_no)->all(), $invoices);
        $this->assertSame('=SUBTOTAL(109,G9:G36)', $sheet->getCell('G'.($last + 1))->getValue());
        $this->assertSame('A9', $sheet->getFreezePane());
        $this->assertSame('A8:N36', $sheet->getAutoFilter()->getRange());

        // المحددة بس
        $sheet = $this->exportBook(['ids' => [$due->id]]);
        $this->assertSame($due->invoice_no, $sheet->getCell('B9')->getValue());
        $this->assertSame(250.0, (float) $sheet->getCell('I9')->getValue());
        $this->assertSame(__('sell_list.xl_total_row'), $sheet->getCell('A10')->getValue());
    }

    public function test_csv_export_keeps_text_safe_and_applies_the_tab(): void
    {
        $this->makeSell(100, 100, 'paid');
        $due = $this->makeSell(300, 0, 'due', ['additional_notes' => '=HYPERLINK("x")']);

        $csv = $this->get(route('sells.v2.export', $this->range + ['format' => 'csv', 'payment_status' => 'due']))
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($due->invoice_no, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame(2, count(array_filter(explode("\n", trim($csv)))));
    }

    public function test_print_covers_the_whole_period(): void
    {
        $sells = collect();
        for ($i = 0; $i < 27; $i++) {
            $sells->push($this->makeSell(10, 10, 'paid'));
        }

        $html = $this->get(route('sells.v2.export', $this->range + ['format' => 'print']))->assertOk()->getContent();

        foreach ($sells as $sell) {
            $this->assertStringContainsString($sell->invoice_no, $html);
        }
        $this->assertStringContainsString(__('sell_list.xl_total_row'), $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_invoice_window_v2_shows_totals_and_payments(): void
    {
        $sell = $this->makeSell(200, 50, 'partial');

        $html = $this->get('/sells/'.$sell->id.'?v2=1')->assertOk()->getContent();

        $this->assertStringContainsString('id="rr_invoice_'.$sell->id.'"', $html);
        $this->assertStringContainsString($sell->invoice_no, $html);
        $this->assertStringContainsString(__('lang_v1.partial'), $html);
        $this->assertStringContainsString(__('sell_list.inv_payments'), $html);
        $this->assertStringContainsString('add_payment_modal', $html);
    }

    public function test_invoice_window_stays_classic_without_the_flag(): void
    {
        $sell = $this->makeSell(100, 100, 'paid');

        $html = $this->get('/sells/'.$sell->id)->assertOk()->getContent();

        $this->assertStringContainsString('sale-view-modal', $html);
        $this->assertStringNotContainsString('rr_invoice_', $html);
    }

    public function test_new_page_renders_and_old_list_still_works(): void
    {
        $this->get(route('sells.v2'))
            ->assertOk()
            ->assertSee(__('sell_list.kpi_total'))
            ->assertSee('id="sell_table"', false)
            ->assertSee('id="rr_sells_kpis"', false);

        $this->get('/sells')->assertOk()->assertSee('id="sell_table"', false);
    }
}
