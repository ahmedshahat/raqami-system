<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactPayModalTest extends TestCase
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

    private function open($contact, $type)
    {
        return $this->withSession([
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ])->get('/payments/pay-contact-due/'.$contact->id.'?type='.$type, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function makeContact($type)
    {
        return Contact::create([
            'business_id' => $this->business->id,
            'type' => $type,
            'contact_type' => 'individual',
            'first_name' => 'Pay Modal',
            'name' => 'Pay Modal',
            'supplier_business_name' => 'Pay Modal Co',
            'contact_id' => 'CPM-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ]);
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

    /**
     * @dataProvider typeProvider
     */
    public function test_window_opens_for_each_payment_type($contact_type, $type)
    {
        $contact = $this->makeContact($contact_type);
        $this->addTransaction($contact, $type, 300);

        $response = $this->open($contact, $type);

        $response->assertOk();
        $response->assertSee(__('contact_pay.title_'.$type));
        $response->assertSee(__('contact_pay.due_'.$type));
        $response->assertSee('id="pay_contact_due_form"', false);
        $response->assertSee('name="due_payment_type" type="hidden" value="'.$type.'"', false);
        $response->assertSee('payment_types_dropdown', false);
        $response->assertSee('payment_amount', false);
        $response->assertSee('id="paid_on"', false);
        $response->assertSee('data-due="300"', false);
    }

    public function typeProvider()
    {
        return [
            'supplier purchases' => ['supplier', 'purchase'],
            'supplier returns' => ['supplier', 'purchase_return'],
            'customer invoices' => ['customer', 'sell'],
            'customer returns' => ['customer', 'sell_return'],
        ];
    }

    public function test_due_includes_the_opening_balance_and_shows_it()
    {
        $contact = $this->makeContact('customer');
        $this->addTransaction($contact, 'sell', 300);
        $this->addTransaction($contact, 'opening_balance', 50);

        $response = $this->open($contact, 'sell');

        $response->assertSee('data-due="350"', false);
        $response->assertSee(__('contact_pay.opening_due'));
    }

    public function test_opening_balance_row_is_hidden_when_nothing_is_left()
    {
        $contact = $this->makeContact('customer');
        $this->addTransaction($contact, 'sell', 300);

        $this->open($contact, 'sell')->assertDontSee(__('contact_pay.opening_due'));
    }

    public function test_return_payment_is_limited_to_the_due_amount()
    {
        $contact = $this->makeContact('supplier');
        $this->addTransaction($contact, 'purchase_return', 120);

        $this->open($contact, 'purchase_return')->assertSee('data-rule-max-value="120"', false);
    }

    public function test_saving_with_the_window_fields_records_the_payment()
    {
        $contact = $this->makeContact('customer');
        $invoice = $this->addTransaction($contact, 'sell', 200);

        $response = $this->withSession([
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ])->post('/payments/pay-contact-due', [
            'contact_id' => $contact->id,
            'due_payment_type' => 'sell',
            'amount' => '80',
            'method' => 'cash',
            'paid_on' => now()->format($this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i')),
            'note' => 'from the new window',
        ]);

        $response->assertRedirect();
        $this->assertTrue(session('status')['success'], json_encode(session('status')));
        $this->assertEquals(80, TransactionPayment::where('transaction_id', $invoice->id)->sum('amount'));
        $this->assertSame('partial', $invoice->fresh()->payment_status);
        $this->assertDatabaseHas('transaction_payments', ['payment_for' => $contact->id, 'note' => 'from the new window']);
    }

    /**
     * @dataProvider returnTypeProvider
     */
    public function test_return_due_does_not_include_the_opening_balance($contact_type, $type)
    {
        $contact = $this->makeContact($contact_type);
        $this->addTransaction($contact, $type, 120);
        $this->addTransaction($contact, 'opening_balance', 50);

        $response = $this->open($contact, $type);

        $response->assertSee('data-due="120"', false);
        $response->assertSee('data-rule-max-value="120"', false);
        $response->assertDontSee(__('contact_pay.opening_due'));
    }

    public function returnTypeProvider()
    {
        return [
            'supplier returns' => ['supplier', 'purchase_return'],
            'customer returns' => ['customer', 'sell_return'],
        ];
    }

    /**
     * @dataProvider returnTypeProvider
     */
    public function test_return_payment_goes_to_the_return_and_not_the_opening_balance($contact_type, $type)
    {
        $contact = $this->makeContact($contact_type);
        $return = $this->addTransaction($contact, $type, 100);
        $opening = $this->addTransaction($contact, 'opening_balance', 50);
        $opening->transaction_date = now()->subYear();
        $opening->save();

        $response = $this->savePayment($contact, $type, '100');

        $this->assertTrue(session('status')['success'], json_encode(session('status')));
        $this->assertEquals(100, TransactionPayment::where('transaction_id', $return->id)->sum('amount'));
        $this->assertSame('paid', $return->fresh()->payment_status);
        $this->assertEquals(0, TransactionPayment::where('transaction_id', $opening->id)->sum('amount'));
        $this->assertSame('due', $opening->fresh()->payment_status);
    }

    public function test_invoice_payment_still_pays_the_older_opening_balance_first()
    {
        $contact = $this->makeContact('customer');
        $invoice = $this->addTransaction($contact, 'sell', 100);
        $opening = $this->addTransaction($contact, 'opening_balance', 50);
        $opening->transaction_date = now()->subYear();
        $opening->save();

        $this->savePayment($contact, 'sell', '70');

        $this->assertTrue(session('status')['success'], json_encode(session('status')));
        $this->assertEquals(50, TransactionPayment::where('transaction_id', $opening->id)->sum('amount'));
        $this->assertEquals(20, TransactionPayment::where('transaction_id', $invoice->id)->sum('amount'));
    }

    private function savePayment($contact, $type, $amount)
    {
        return $this->withSession([
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ])->post('/payments/pay-contact-due', [
            'contact_id' => $contact->id,
            'due_payment_type' => $type,
            'amount' => $amount,
            'method' => 'cash',
            'paid_on' => now()->format($this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i')),
        ]);
    }

    public function test_window_does_not_open_for_a_contact_of_another_business()
    {
        $other_business = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $contact = $this->makeContact('customer');
        $contact->business_id = $other_business->id;
        $contact->save();

        $response = $this->open($contact, 'sell');

        $response->assertNotFound();
        $response->assertDontSee('pay_contact_due_form', false);
    }

    public function test_invoice_payment_has_no_maximum()
    {
        $contact = $this->makeContact('customer');
        $this->addTransaction($contact, 'sell', 120);

        $this->open($contact, 'sell')->assertDontSee('data-rule-max-value', false);
    }
}
