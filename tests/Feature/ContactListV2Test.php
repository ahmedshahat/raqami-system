<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Exports\ContactsListExport;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactListV2Test extends TestCase
{
    use DatabaseTransactions;

    private $business;

    private $user;

    // بحث بكود مميز عشان الملخص يحسب جهات الاختبار بس
    private $mark;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($this->business->owner_id);
        $this->actingAs($this->user);
        $this->mark = 'CLV2'.uniqid();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    private function sessionData()
    {
        return ['user.business_id' => $this->business->id, 'business' => $this->business->toArray(), 'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.']];
    }

    private function makeContact($type, $extra = [])
    {
        return Contact::create(array_merge([
            'business_id' => $this->business->id,
            'type' => $type,
            'name' => 'Test '.$this->mark,
            'contact_id' => $this->mark.'-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ], $extra));
    }

    private function makeDoc($contact, $type, $total, $paid = 0, $status = 'final')
    {
        $doc = Transaction::create([
            'business_id' => $this->business->id,
            'location_id' => $this->business->locations()->firstOrFail()->id,
            'type' => $type,
            'status' => $status,
            'payment_status' => 'due',
            'contact_id' => $contact->id,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'total_before_tax' => $total,
            'final_total' => $total,
            'created_by' => $this->user->id,
        ]);
        if ($paid > 0) {
            TransactionPayment::create([
                'transaction_id' => $doc->id,
                'business_id' => $this->business->id,
                'amount' => $paid,
                'method' => 'cash',
                'paid_on' => now()->format('Y-m-d H:i:s'),
                'created_by' => $this->user->id,
                'payment_for' => $contact->id,
            ]);
        }

        return $doc;
    }

    private function summary($type, $params = [])
    {
        return $this->withSession($this->sessionData())
            ->getJson(route('contacts.list_summary', array_merge(['type' => $type, 'search' => $this->mark], $params)))
            ->assertOk();
    }

    public function test_supplier_summary_totals_match_the_list_formulas(): void
    {
        $a = $this->makeContact('supplier', ['balance' => 40]);
        $this->makeDoc($a, 'purchase', 1000, 300);
        $this->makeDoc($a, 'purchase_return', 100, 25);
        $b = $this->makeContact('supplier');
        $this->makeDoc($b, 'opening_balance', 200, 50);
        $paid_in_full = $this->makeContact('supplier', ['contact_status' => 'inactive']);
        $this->makeDoc($paid_in_full, 'purchase', 500, 500);

        $this->summary('supplier')->assertJson([
            'contacts' => 3,
            'active' => 2,
            'due' => 775,
            'return_due' => 75,
            'advance' => 40,
            'opening_left' => 150,
            'counts' => ['all' => 3, 'due' => 2, 'advance' => 1, 'returns' => 1, 'opening' => 1],
        ]);
    }

    public function test_customer_summary_nets_returns_and_ignores_draft_sells(): void
    {
        $a = $this->makeContact('customer');
        $this->makeDoc($a, 'sell', 900, 200);
        $this->makeDoc($a, 'sell_return', 100);
        $this->makeDoc($a, 'sell', 5000, 0, 'draft');
        // عميل دفع أكتر من فواتيره: رصيده السالب ما بيقللش إجمالي المستحق
        $over = $this->makeContact('customer');
        $this->makeDoc($over, 'sell', 100, 150);

        $this->summary('customer')->assertJson([
            'contacts' => 2,
            'due' => 600,
            'return_due' => 100,
            'counts' => ['due' => 1, 'returns' => 1],
        ]);
    }

    public function test_summary_applies_status_filter_and_stays_inside_the_business(): void
    {
        $this->makeContact('supplier');
        $this->makeContact('supplier', ['contact_status' => 'inactive']);
        $other = Business::where('id', '!=', $this->business->id)->first();
        if ($other) {
            $this->makeContact('supplier', ['business_id' => $other->id]);
        }

        $this->summary('supplier')->assertJson(['contacts' => 2]);
        $this->summary('supplier', ['contact_status' => 'inactive'])->assertJson(['contacts' => 1, 'active' => 0]);
    }

    public function test_type_is_required_and_export_needs_the_permission(): void
    {
        $this->withSession($this->sessionData())->getJson(route('contacts.list_summary', ['type' => 'lead']))->assertNotFound();

        $limited = User::where('business_id', $this->business->id)->where('id', '!=', $this->user->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'like', 'Admin%'))->first();
        if (! $limited || $limited->can('view_export_buttons')) {
            $this->markTestSkipped('No limited user in the seed data.');
        }
        $this->actingAs($limited)->withSession($this->sessionData())
            ->get(route('contacts.list_export', ['type' => 'supplier']))->assertForbidden();
    }

    public function test_ajax_list_can_sort_by_due(): void
    {
        $small = $this->makeContact('customer', ['name' => 'Small '.$this->mark]);
        $this->makeDoc($small, 'sell', 100);
        $big = $this->makeContact('customer', ['name' => 'Big '.$this->mark]);
        $this->makeDoc($big, 'sell', 900);

        $columns = [
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'due', 'name' => 'due', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'contact_id', 'name' => 'contact_id', 'searchable' => 'true', 'orderable' => 'true'],
        ];
        $response = $this->withSession($this->sessionData())->getJson('/contacts?'.http_build_query([
            'type' => 'customer',
            'draw' => 1, 'start' => 0, 'length' => 25,
            'columns' => $columns,
            'order' => [['column' => 1, 'dir' => 'desc']],
            'search' => ['value' => $this->mark, 'regex' => 'false'],
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertStringContainsString('Big', $response->json('data.0.name'));
    }

    private function listRows($type, $order_column = 0, $extra = [])
    {
        $columns = [
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'due', 'name' => 'due', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'contact_id', 'name' => 'contact_id', 'searchable' => 'true', 'orderable' => 'true'],
        ];

        return $this->withSession($this->sessionData())->getJson('/contacts?'.http_build_query(array_merge([
            'type' => $type,
            'draw' => 1, 'start' => 0, 'length' => 25,
            'columns' => $columns,
            'order' => [['column' => $order_column, 'dir' => 'desc']],
            'search' => ['value' => $this->mark, 'regex' => 'false'],
        ], $extra)), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    public function test_due_column_filter_and_sort_include_what_is_left_of_the_opening_balance(): void
    {
        $paid = $this->makeContact('customer', ['name' => 'Paid '.$this->mark]);
        $this->makeDoc($paid, 'opening_balance', 300, 300);
        $small = $this->makeContact('customer', ['name' => 'Small '.$this->mark]);
        $this->makeDoc($small, 'sell', 100);
        $opening = $this->makeContact('customer', ['name' => 'Opening '.$this->mark]);
        $this->makeDoc($opening, 'sell', 50);
        $this->makeDoc($opening, 'opening_balance', 900, 200);

        $rows = $this->listRows('customer', 1);
        $this->assertStringContainsString('Opening', $rows[0]['name']);
        $this->assertStringContainsString('data-orig-value="750"', $rows[0]['due']);
        $this->assertStringContainsString('Small', $rows[1]['name']);
        $this->assertStringContainsString('data-orig-value="0"', $rows[2]['due']);

        $with_due = $this->listRows('customer', 1, ['has_sell_due' => 1]);
        $this->assertCount(2, $with_due);

        $this->summary('customer')->assertJson(['due' => 850, 'opening_left' => 700, 'counts' => ['due' => 2]]);
    }

    public function test_supplier_due_column_includes_opening_and_both_type_counts_with_customers_only(): void
    {
        $supplier = $this->makeContact('supplier', ['name' => 'Supplier '.$this->mark]);
        $this->makeDoc($supplier, 'opening_balance', 400, 100);
        $both = $this->makeContact('both', ['name' => 'Both '.$this->mark]);
        $this->makeDoc($both, 'opening_balance', 700);

        $rows = $this->listRows('supplier', 1);
        $this->assertStringContainsString('Supplier', $rows[0]['name']);
        $this->assertStringContainsString('data-orig-value="300"', $rows[0]['due']);
        $this->assertStringContainsString('data-orig-value="0"', $rows[1]['due']);
        $this->assertCount(1, $this->listRows('supplier', 1, ['has_purchase_due' => 1]));
        $this->summary('supplier')->assertJson(['due' => 300, 'opening_left' => 300]);

        $customers = $this->listRows('customer', 1);
        $this->assertCount(1, $customers);
        $this->assertStringContainsString('data-orig-value="700"', $customers[0]['due']);
        $this->summary('customer')->assertJson(['due' => 700, 'opening_left' => 700]);
    }

    public function test_report_columns_add_up_when_a_customer_overpaid_invoices_and_has_an_opening_balance(): void
    {
        $contact = $this->makeContact('customer');
        $this->makeDoc($contact, 'sell', 100, 150);
        $this->makeDoc($contact, 'opening_balance', 200);

        $query = app(\App\Utils\ContactUtil::class)->getContactQuery($this->business->id, 'customer', [$contact->id]);
        $values = (new ContactsListExport($query->get(), ['type' => 'customer']))->reportRecords()[0]['values'];

        $this->assertEquals(150, $values['due']);
        $this->assertEquals(150, $values['total_due']);
        $this->assertEquals(200, $values['opening_left']);
        $this->assertEquals(-50, $values['invoice_due']);
    }

    public function test_new_screen_is_the_default_and_classic_stays_reachable(): void
    {
        $this->withSession($this->sessionData())->get('/contacts?type=supplier')->assertOk()->assertViewIs('contact.index_v2');
        $this->withSession($this->sessionData())->get('/contacts?type=customer')->assertOk()->assertViewIs('contact.index_v2');
        $this->withSession($this->sessionData())->get('/contacts?type=supplier&classic=1')->assertOk()->assertViewIs('contact.index');
    }

    private function report($type, $params = [])
    {
        return $this->withSession($this->sessionData())
            ->get(route('contacts.list_export', array_merge(['type' => $type, 'search' => $this->mark, 'format' => 'print'], $params)))
            ->assertOk()->assertViewIs('contact.print_list');
    }

    public function test_due_report_keeps_opening_only_contacts_and_sorts_by_highest(): void
    {
        $small = $this->makeContact('customer', ['name' => 'Small '.$this->mark]);
        $this->makeDoc($small, 'sell', 100);
        $opening_only = $this->makeContact('customer', ['name' => 'Opening '.$this->mark]);
        $this->makeDoc($opening_only, 'opening_balance', 500, 100);
        $paid = $this->makeContact('customer', ['name' => 'Paid '.$this->mark]);
        $this->makeDoc($paid, 'sell', 300, 300);
        $over = $this->makeContact('customer', ['name' => 'Over '.$this->mark]);
        $this->makeDoc($over, 'sell', 100, 150);

        $records = $this->report('customer', ['scope' => 'due'])->viewData('records');

        $this->assertCount(2, $records);
        $this->assertStringContainsString('Opening', $records[0]['values']['name']);
        $this->assertEquals(1, $records[0]['values']['n']);
        $this->assertEquals(400, $records[0]['values']['total_due']);
        $this->assertEquals(100, $records[1]['values']['total_due']);
    }

    public function test_full_report_total_matches_the_screen_kpis(): void
    {
        $a = $this->makeContact('supplier');
        $this->makeDoc($a, 'purchase', 1000, 300);
        $b = $this->makeContact('supplier');
        $this->makeDoc($b, 'opening_balance', 200, 50);
        $this->makeContact('supplier');

        $records = $this->report('supplier')->assertSee(__('contact_list.report_total_due_supplier'))->viewData('records');
        $kpis = $this->summary('supplier')->json();

        $this->assertCount(3, $records);
        $this->assertEquals($kpis['due'], array_sum(array_map(fn ($r) => $r['values']['total_due'], $records)));
        $this->assertEquals($kpis['opening_left'], array_sum(array_map(fn ($r) => $r['values']['opening_left'], $records)));
        $this->assertEquals(850, array_sum(array_map(fn ($r) => $r['values']['total_due'], $records)));
    }

    public function test_report_columns_are_whitelisted_and_default_when_missing(): void
    {
        $this->makeContact('supplier');

        $this->assertEquals(['mobile', 'advance'], array_keys($this->report('supplier')->viewData('columns')));
        $this->assertEquals(['tax_number'], array_keys($this->report('supplier', ['cols' => 'tax_number,password,group'])->viewData('columns')));
        $this->assertSame([], $this->report('supplier', ['cols' => ''])->viewData('columns'));
    }

    public function test_excel_report_matches_the_printed_report(): void
    {
        $owing = $this->makeContact('customer', ['name' => '=HYPERLINK("x")', 'mobile' => '0100123']);
        $this->makeDoc($owing, 'sell', 900, 200);
        $opening = $this->makeContact('customer', ['name' => 'Opening '.$this->mark]);
        $this->makeDoc($opening, 'opening_balance', 500, 100);
        $this->makeContact('customer', ['name' => 'Clear '.$this->mark]);

        $this->withSession($this->sessionData())
            ->get(route('contacts.list_export', ['type' => 'customer', 'search' => $this->mark, 'format' => 'report_xlsx', 'scope' => 'due']))
            ->assertOk()->assertDownload('customer_dues_report_'.now()->format('Y-m-d').'.xlsx');

        $query = app(\App\Utils\ContactUtil::class)->getContactQuery($this->business->id, 'customer', [$owing->id, $opening->id]);
        $export = new ContactsListExport($query->get(), ['type' => 'customer', 'precision' => 2, 'symbol' => 'SAR']);
        $options = ContactsListExport::reportColumnOptions('customer');
        $columns = array_intersect_key($options, array_flip(['mobile', 'advance']));
        $sheet = $export->reportSpreadsheet($export->reportRecords(true), $columns, true)->getActiveSheet();

        // الصف 5 رؤوس: # جهة الاتصال الكود الهاتف الفواتير الافتتاحي المقدم الإجمالي
        $this->assertSame(__('contact_list.report_col_total'), $sheet->getCell('H5')->getValue());
        $this->assertEquals(700, $sheet->getCell('H6')->getValue());
        $this->assertSame('0100123', $sheet->getCell('D6')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('B6')->getDataType());
        $this->assertEquals(400, $sheet->getCell('H7')->getValue());
        $this->assertEquals(1100, $sheet->getCell('H8')->getCalculatedValue());
        // الملخص: العدد ثم الفواتير ثم الافتتاحي ثم الإجمالي الكبير
        $this->assertEquals(2, $sheet->getCell('H10')->getCalculatedValue());
        $this->assertEquals(700, $sheet->getCell('H11')->getCalculatedValue());
        $this->assertEquals(400, $sheet->getCell('H12')->getCalculatedValue());
        $this->assertEquals(1100, $sheet->getCell('H13')->getCalculatedValue());
        $this->assertSame(__('contact_list.report_total_due_customer'), $sheet->getCell('F13')->getValue());
    }

    // ملف بنفس أعمدة النموذج (27): كل صف [النوع، الاسم، الكود، الافتتاحي، مدة السداد، وحدتها، الهاتف]
    private function importFile(array $rows, $columns = 27)
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue([$columns, 1], 'head');
        foreach ($rows as $i => [$type, $name, $code, $opening, $term, $period, $mobile]) {
            $line = $i + 2;
            foreach ([1 => $type, 3 => $name, 7 => $code, 9 => $opening, 10 => $term, 11 => $period, 14 => $mobile] as $column => $value) {
                $sheet->setCellValue([$column, $line], $value);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return new \Illuminate\Http\UploadedFile($path, 'contacts.xlsx', null, null, true);
    }

    private function postImport($file)
    {
        // الرصيد الافتتاحي بيقرا time_zone من session('business') كـ object زي الجلسة الحقيقية
        return $this->withSession(['business' => $this->business, 'user.id' => $this->user->id] + $this->sessionData())
            ->post('/contacts/import', ['contacts_csv' => $file], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    public function test_import_page_opens_the_window_on_the_list(): void
    {
        $this->withSession($this->sessionData())->get('/contacts/import')
            ->assertRedirect(action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => 'customer', 'import' => 1]));
        $this->withSession($this->sessionData())->get('/contacts?type=customer&import=1')
            ->assertOk()->assertSee('rr_contacts_import_modal', false)->assertSee('openImport();', false);
    }

    public function test_sidebar_has_no_import_link(): void
    {
        $this->withSession($this->sessionData())->get('/contacts?type=customer')->assertOk()
            ->assertSee(__('contact_list.menu_customer_groups'))
            ->assertDontSee(__('lang_v1.import_contacts'));
    }

    public function test_import_saves_all_rows_and_reports_the_count(): void
    {
        $response = $this->postImport($this->importFile([
            [1, 'Imp '.$this->mark, $this->mark.'-A', 250, null, null, '0501'],
            [2, 'Sup '.$this->mark, $this->mark.'-B', null, 30, 'days', '0502'],
        ]));

        $response->assertJson(['success' => 1, 'msg' => __('contact_list.import_done', ['count' => 2])]);
        $customer = Contact::where('business_id', $this->business->id)->where('contact_id', $this->mark.'-A')->firstOrFail();
        $this->assertSame('customer', $customer->type);
        $this->assertEquals(250, Transaction::where('contact_id', $customer->id)->where('type', 'opening_balance')->value('final_total'));
        $this->assertSame('supplier', Contact::where('contact_id', $this->mark.'-B')->value('type'));
    }

    public function test_import_error_names_the_excel_row_and_saves_nothing(): void
    {
        $response = $this->postImport($this->importFile([
            [1, 'Imp '.$this->mark, $this->mark.'-A', null, null, null, '0501'],
            [1, 'Bad '.$this->mark, $this->mark.'-B', null, null, null, null],
        ]));

        $response->assertJson(['success' => 0, 'msg' => __('contact_list.import_err_mobile', ['row' => 3])]);
        $this->assertSame(0, Contact::where('contact_id', 'like', $this->mark.'%')->count());
    }

    public function test_import_rejects_wrong_columns_empty_and_unreadable_files(): void
    {
        $this->postImport($this->importFile([[1, 'X', null, null, null, null, '05']], 30))
            ->assertJson(['success' => 0, 'msg' => __('contact_list.import_err_columns')]);
        $this->postImport($this->importFile([]))
            ->assertJson(['success' => 0, 'msg' => __('contact_list.import_empty')]);

        $bad = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        file_put_contents($bad, 'not a spreadsheet');
        $this->postImport(new \Illuminate\Http\UploadedFile($bad, 'bad.xlsx', null, null, true))
            ->assertJson(['success' => 0, 'msg' => __('contact_list.import_failed')]);
    }

    public function test_export_records_use_the_list_formulas(): void
    {
        $contact = $this->makeContact('supplier', ['balance' => 15]);
        $this->makeDoc($contact, 'purchase', 300, 100);

        $query = app(\App\Utils\ContactUtil::class)->getContactQuery($this->business->id, 'supplier', [$contact->id]);
        $values = (new ContactsListExport($query->get(), ['type' => 'supplier']))->records()[0]['values'];

        $this->assertEquals(200, $values['due']);
        $this->assertEquals(15, $values['advance']);
    }

    public function test_supplier_due_is_net_of_unsettled_purchase_returns(): void
    {
        $with_return = $this->makeContact('supplier');
        $this->makeDoc($with_return, 'purchase', 1000, 200);
        $this->makeDoc($with_return, 'purchase_return', 300, 100);
        $returned_all = $this->makeContact('supplier');
        $this->makeDoc($returned_all, 'purchase', 100);
        $this->makeDoc($returned_all, 'purchase_return', 100);

        $rows = $this->listRows('supplier', 1);
        $this->assertStringContainsString('data-orig-value="600"', $rows[0]['due']);
        $this->assertCount(1, $this->listRows('supplier', 1, ['has_purchase_due' => 1]));
        $this->summary('supplier')->assertJson(['due' => 600, 'return_due' => 300, 'counts' => ['due' => 1]]);

        $this->assertEquals(600, (float) app(\App\Utils\Util::class)->getContactDue($with_return->id, $this->business->id));
        $this->assertEquals(600, (float) app(\App\Utils\TransactionUtil::class)->getLedgerDetails($with_return->id, now()->subYear()->format('Y-m-d'), now()->format('Y-m-d'))['all_balance_due']);
    }

    public function test_contact_due_subtracts_ledger_discounts_like_the_ledger(): void
    {
        $customer = $this->makeContact('customer');
        $this->makeDoc($customer, 'sell', 1000, 200);
        $this->makeDoc($customer, 'ledger_discount', 100);

        $this->summary('customer')->assertJson(['due' => 700]);
        $this->assertEquals(700, (float) app(\App\Utils\Util::class)->getContactDue($customer->id, $this->business->id));
        $this->assertEquals(700, (float) app(\App\Utils\TransactionUtil::class)->getLedgerDetails($customer->id, now()->subYear()->format('Y-m-d'), now()->format('Y-m-d'))['all_balance_due']);
    }
}
