<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\InvoiceLayout;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RaqmiInvoicePrintTest extends TestCase
{
    use DatabaseTransactions;

    public function test_raqmi_design_prints_the_invoice(): void
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $location = $business->locations()->firstOrFail();
        $contact = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();

        $sell = Transaction::create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'type' => 'sell',
            'status' => 'final',
            'is_direct_sale' => 1,
            'payment_status' => 'partial',
            'contact_id' => $contact->id,
            'invoice_no' => 'RQI-TEST-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => 500,
            'final_total' => 500,
            'created_by' => $user->id,
        ]);
        TransactionPayment::create([
            'transaction_id' => $sell->id,
            'business_id' => $business->id,
            'amount' => 200,
            'method' => 'cash',
            'paid_on' => now(),
            'created_by' => $user->id,
            'payment_for' => $contact->id,
        ]);

        $layoutId = $location->sale_invoice_layout_id ?: $location->invoice_layout_id;
        InvoiceLayout::where('id', $layoutId)->update(['design' => 'raqmi']);

        $html = $this->getJson('/sells/'.$sell->id.'/print', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('receipt.html_content');

        $this->assertStringContainsString('class="rqi"', $html);
        $this->assertStringContainsString($sell->invoice_no, $html);
        $this->assertStringContainsString('rqi-badge--partial', $html);
        $this->assertStringContainsString('rqi-grand', $html);
    }
}
