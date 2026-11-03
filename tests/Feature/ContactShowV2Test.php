<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactShowV2Test extends TestCase
{
    use DatabaseTransactions;

    private $business;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($this->business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    private function page()
    {
        return $this->withSession([
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ]);
    }

    private function makeContact($extra = [])
    {
        return Contact::create(array_merge([
            'business_id' => $this->business->id,
            'type' => 'customer',
            'contact_type' => 'individual',
            'first_name' => 'Show',
            'name' => 'Show',
            'contact_id' => 'CSV2-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ], $extra));
    }

    private function addTransaction($contact, $type, $total)
    {
        return Transaction::create([
            'business_id' => $this->business->id,
            'location_id' => $this->business->locations()->first()->id,
            'contact_id' => $contact->id,
            'type' => $type,
            'status' => 'final',
            'payment_status' => 'due',
            'final_total' => $total,
            'total_before_tax' => $total,
            'transaction_date' => now(),
            'created_by' => $this->user->id,
        ]);
    }

    public function test_page_opens_the_new_design_and_keeps_the_old_one(): void
    {
        $contact = $this->makeContact();

        $this->page()->get('/contacts/'.$contact->id)->assertOk()->assertViewIs('contact.show_v2')->assertSee('id="rr_contact_switch"', false);
        $this->page()->get('/contacts/'.$contact->id.'?classic=1')->assertOk()->assertViewIs('contact.show');
    }

    public function test_customer_summary_adds_opening_balance_and_matches_the_ledger(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 300);
        $this->addTransaction($contact, 'opening_balance', 200);

        $response = $this->page()->get('/contacts/'.$contact->id)->assertOk();
        $summary = $response->viewData('summary');

        $this->assertEquals(500, $summary['all_total_invoice']);
        $this->assertEquals(0, $summary['all_invoice_paid']);
        $this->assertEquals(500, $summary['all_balance_due']);
        $response->assertSee('pay_sale_due', false)->assertDontSee('pay_purchase_due', false);
    }

    public function test_supplier_page_shows_supplier_tabs_and_pay_button(): void
    {
        $contact = $this->makeContact(['type' => 'supplier']);
        $this->addTransaction($contact, 'purchase', 150);

        $response = $this->page()->get('/contacts/'.$contact->id)->assertOk();

        $this->assertEquals(150, $response->viewData('summary')['all_balance_due']);
        $response->assertSee('id="purchases_tab"', false)
            ->assertSee('id="stock_report_tab"', false)
            ->assertDontSee('id="sales_tab"', false)
            ->assertSee('pay_purchase_due', false);
    }

    public function test_pay_button_is_hidden_without_a_due(): void
    {
        $this->page()->get('/contacts/'.$this->makeContact()->id)->assertOk()->assertDontSee('pay_sale_due', false);
    }

    public function test_details_show_only_filled_fields_and_escape_them(): void
    {
        $contact = $this->makeContact(['email' => 'a&b@test.local', 'city' => 'Jeddah', 'street_name' => '<b>Main</b>']);

        $this->page()->get('/contacts/'.$contact->id)->assertOk()
            ->assertSee('a&amp;b@test.local', false)
            ->assertSee('&lt;b&gt;Main&lt;/b&gt;', false)
            ->assertDontSee(__('contact_show.landline'));
    }

    private function uncoveredText($contact)
    {
        $text = strip_tags($this->page()->get('/contacts/'.$contact->id)->assertOk()->getContent());
        $this->assertSame(1, preg_match('/'.preg_quote(__('cheque.uncovered_due'), '/').':[^\d]*([\d.,]+)/u', $text, $match));

        return (float) str_replace(',', '', $match[1]);
    }

    public function test_cheque_card_shows_the_due_not_covered_by_cheques(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 1000);
        \App\Cheque::register($this->business->id, 'receivable', $contact->id, null, ['amount' => 700]);

        $this->assertEquals(300, $this->uncoveredText($contact));
    }

    public function test_uncovered_due_is_never_negative(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 100);
        \App\Cheque::register($this->business->id, 'receivable', $contact->id, null, ['amount' => 700]);

        $this->assertEquals(0, $this->uncoveredText($contact));
    }

    private function ledger($contact, $format, $ui = 'v2')
    {
        return $this->page()->get('/contacts/ledger?contact_id='.$contact->id.'&format='.$format.'&ui='.$ui.'&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->addDay()->format('Y-m-d'));
    }

    public function test_new_page_ledger_uses_the_screen_table_and_the_rest_keep_the_old_templates(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 300);

        foreach (['format_1', 'format_3'] as $format) {
            $this->ledger($contact, $format)->assertOk()
                ->assertViewIs('contact.partials.ledger_screen')
                ->assertSee('rr-cs__ledger-table', false)
                ->assertSee(__('contact_show.kind_sell'))
                ->assertSee('300');
        }

        $this->ledger($contact, 'format_2')->assertOk()->assertViewIs('contact.ledger_format_2');
        $this->ledger($contact, 'format_4')->assertOk()->assertViewIs('contact.ledger_format_4');
        $this->ledger($contact, 'format_1', '')->assertOk()->assertViewIs('contact.ledger');
    }

    public function test_ledger_without_moves_shows_the_empty_state(): void
    {
        $this->ledger($this->makeContact(), 'format_1')->assertOk()
            ->assertSee(__('contact_show.no_moves'))
            ->assertDontSee('id="ledger_table"', false);
    }

    public function test_ledger_note_is_escaped(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 300)->update(['additional_notes' => '<script>alert(1)</script>']);

        $this->ledger($contact, 'format_1')->assertOk()->assertDontSee('<script>alert(1)', false);
    }

    public function test_main_facts_show_in_the_head_and_the_rest_under_all_details(): void
    {
        $contact = $this->makeContact(['mobile' => '0555111222', 'city' => 'Jeddah']);

        $this->page()->get('/contacts/'.$contact->id)->assertOk()
            ->assertSee('href="tel:0555111222"', false)
            ->assertSee(__('contact_show.all_details'))
            ->assertSee('Jeddah');
    }

    public function test_sales_tab_has_the_report_buttons_and_a_footer_cell_for_each_column(): void
    {
        $response = $this->page()->get('/contacts/'.$this->makeContact()->id)->assertOk()
            ->assertSee('data-rr-sales-col="3"', false)
            ->assertSee('data-rr-sales-export="print"', false)
            ->assertSee('data-rr-sales-export="excel"', false);

        $this->assertSame(1, preg_match('/id="sell_table".*?<tfoot>(.*?)<\/tfoot>/s', $response->getContent(), $footer));
        $this->assertSame(22, substr_count($footer[1], '<td'));
        $this->assertStringNotContainsString('colspan', $footer[1]);

        $classic = $this->page()->get('/contacts/'.$this->makeContact()->id.'?classic=1')->assertOk();
        $classic->assertDontSee('data-rr-sales-export', false)->assertSee('colspan="6"', false);
    }

    public function test_sales_report_only_has_the_invoices_of_this_customer(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($this->makeContact(), 'sell', 111)->update(['invoice_no' => 'RRCS-OTHER']);
        $this->addTransaction($contact, 'sell', 222)->update(['invoice_no' => 'RRCS-MINE']);

        $this->page()->get('/sells/v2/export?format=print&customer_id='.$contact->id)->assertOk()
            ->assertSee('RRCS-MINE')
            ->assertDontSee('RRCS-OTHER');
    }

    public function test_purchases_tab_has_the_report_buttons_and_a_footer_cell_for_each_column(): void
    {
        $supplier = $this->makeContact(['type' => 'supplier']);
        $response = $this->page()->get('/contacts/'.$supplier->id)->assertOk()
            ->assertSee('data-rr-purchase-col="3"', false)
            ->assertSee('data-rr-purchase-export="print"', false)
            ->assertSee('data-rr-purchase-export="excel"', false)
            ->assertSee('id="purchase_list_filter_payment_status"', false);

        $this->assertSame(1, preg_match('/id="purchase_table".*?<tfoot>(.*?)<\/tfoot>/s', $response->getContent(), $footer));
        $this->assertSame(14, substr_count($footer[1], '<td'));
        $this->assertStringNotContainsString('colspan', $footer[1]);

        $classic = $this->page()->get('/contacts/'.$supplier->id.'?classic=1')->assertOk();
        $classic->assertDontSee('data-rr-purchase-export', false)->assertSee('colspan="5"', false);
    }

    public function test_purchases_report_only_has_this_supplier_and_follows_the_filters(): void
    {
        $supplier = $this->makeContact(['type' => 'supplier']);
        $this->addTransaction($this->makeContact(['type' => 'supplier']), 'purchase', 111)->update(['ref_no' => 'RRPU-OTHER', 'status' => 'received']);
        $this->addTransaction($supplier, 'purchase', 222)->update(['ref_no' => 'RRPU-DUE', 'status' => 'received']);
        $this->addTransaction($supplier, 'purchase', 333)->update(['ref_no' => 'RRPU-PAID', 'status' => 'received', 'payment_status' => 'paid']);

        $this->page()->get('/purchases/export?format=print&supplier_id='.$supplier->id)->assertOk()
            ->assertSee(__('purchase_list.xl_title'))
            ->assertSee('RRPU-DUE')
            ->assertSee('RRPU-PAID')
            ->assertSee(__('lang_v1.received'))
            ->assertDontSee('RRPU-OTHER');

        $this->page()->get('/purchases/export?format=print&payment_status=paid&supplier_id='.$supplier->id)->assertOk()
            ->assertSee('RRPU-PAID')
            ->assertDontSee('RRPU-DUE');

        $this->page()->get('/purchases/export?format=print&search=RRPU-DUE&supplier_id='.$supplier->id)->assertOk()
            ->assertSee('RRPU-DUE')
            ->assertDontSee('RRPU-PAID');
    }

    public function test_purchases_report_ignores_another_business_and_downloads_as_excel(): void
    {
        $other_business = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $other = $this->makeContact(['type' => 'supplier', 'business_id' => $other_business->id]);
        Transaction::where('id', $this->addTransaction($other, 'purchase', 50)->id)
            ->update(['business_id' => $other_business->id, 'ref_no' => 'RRPU-FOREIGN']);

        $this->page()->get('/purchases/export?format=print&supplier_id='.$other->id)->assertOk()
            ->assertDontSee('RRPU-FOREIGN');

        $supplier = $this->makeContact(['type' => 'supplier']);
        $this->addTransaction($supplier, 'purchase', 222)->update(['ref_no' => '=RRPU-X', 'status' => 'received']);
        $excel = $this->page()->get('/purchases/export?format=excel&supplier_id='.$supplier->id)->assertOk();
        $this->assertStringStartsWith('PK', $excel->streamedContent());
    }

    public function test_statement_print_page_works_for_all_formats_and_escapes_notes(): void
    {
        $contact = $this->makeContact(['type' => 'customer']);
        $this->addTransaction($contact, 'sell', 300)->update(['invoice_no' => 'RRST-1', 'additional_notes' => '<script>alert(1)</script>']);

        foreach (['format_1', 'format_2', 'format_3', 'format_4'] as $format) {
            $this->page()->get('/contacts/ledger?action=print&contact_id='.$contact->id.'&format='.$format.'&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->addDay()->format('Y-m-d'))
                ->assertOk()
                ->assertViewIs('contact.ledger_statement')
                ->assertSee(__('contact_statement.title'))
                ->assertSee('RRST-1')
                ->assertDontSee('<script>alert(1)', false);
        }
    }

    public function test_statement_shows_the_arabic_address_intact(): void
    {
        $contact = $this->makeContact(['type' => 'customer', 'address_line_1' => 'امام الكنيسة', 'city' => 'جسر السويس']);

        $this->page()->get('/contacts/ledger?action=print&contact_id='.$contact->id.'&format=format_1&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->addDay()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('امام الكنيسة، جسر السويس');
    }

    public function test_statement_lists_pending_cheques_and_the_uncovered_due(): void
    {
        $contact = $this->makeContact(['type' => 'customer']);
        $this->addTransaction($contact, 'sell', 1000);
        \App\Cheque::forceCreate([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'type' => 'receivable',
            'status' => \App\Cheque::STATUS_PENDING,
            'cheque_number' => 'RRCHQ-1',
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'amount' => 400,
        ]);

        $this->page()->get('/contacts/ledger?action=print&contact_id='.$contact->id.'&format=format_1&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->addDay()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('RRCHQ-1')
            ->assertSee(__('cheque.uncovered_due'))
            ->assertSee(__('cheque.on_account'));
    }

    public function test_statement_of_another_business_contact_is_not_found(): void
    {
        $other_business = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $contact = $this->makeContact(['business_id' => $other_business->id]);
        $query = 'contact_id='.$contact->id.'&format=format_1&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->addDay()->format('Y-m-d');

        foreach (['', '&action=print', '&action=pdf', '&ui=v2'] as $extra) {
            $this->page()->get('/contacts/ledger?'.$query.$extra)->assertNotFound();
        }
    }

    public function test_email_refuses_another_business_contact_and_sends_for_own(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $payload = [
            'to_email' => 'statement@example.test',
            'subject' => 'Statement',
            'email_body' => 'Balance {balance_due}',
            'ledger_format' => 'format_1',
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ];

        $other_business = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $other = $this->makeContact(['business_id' => $other_business->id]);
        $this->page()->post('/contacts/send-ledger', $payload + ['contact_id' => $other->id])->assertNotFound();
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        $own = $this->makeContact();
        $this->addTransaction($own, 'sell', 300);
        $this->page()->post('/contacts/send-ledger', $payload + ['contact_id' => $own->id])
            ->assertOk()
            ->assertJson(['success' => 1]);
        \Illuminate\Support\Facades\Notification::assertSentTimes(\App\Notifications\CustomerNotification::class, 1);
    }

    public function test_statement_opens_as_a_real_pdf(): void
    {
        $contact = $this->makeContact();
        $this->addTransaction($contact, 'sell', 300);
        $this->page()->get('/contacts/'.$contact->id)->assertOk();

        $details = app(\App\Utils\TransactionUtil::class)->getLedgerDetails($contact->id, now()->subYear()->format('Y-m-d'), now()->addDay()->format('Y-m-d'), 'format_1', null);
        session(['business.currency_precision' => 2, 'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.']]);

        $method = new \ReflectionMethod(\App\Http\Controllers\ContactController::class, 'ledgerStatementPdf');
        $method->setAccessible(true);
        $mpdf = $method->invoke(app(\App\Http\Controllers\ContactController::class), $details, $contact->fresh(), null, 'format_1');

        $this->assertStringStartsWith('%PDF', $mpdf->Output('', 'S'));
    }

    public function test_unknown_view_falls_back_to_ledger(): void
    {
        $this->page()->get('/contacts/'.$this->makeContact()->id.'?view=nonsense')->assertOk()
            ->assertSee('class="tab-pane active" id="ledger_tab"', false);
    }

    public function test_other_business_contact_is_not_shown(): void
    {
        $other = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $contact = Contact::where('business_id', $other->id)->firstOrFail();

        $this->page()->get('/contacts/'.$contact->id)->assertNotFound();
    }
}
