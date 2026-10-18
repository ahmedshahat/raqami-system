<?php

namespace Tests\Feature;

use App\Business;
use App\Cheque;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use App\Utils\ChequeUtil;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChequeRegisterTest extends TestCase
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

    private function makeTransaction($type, $total)
    {
        $contact = Contact::where('business_id', $this->business->id)
            ->whereIn('type', $type == 'sell' ? ['customer', 'both'] : ['supplier', 'both'])
            ->firstOrFail();

        return Transaction::create([
            'business_id' => $this->business->id,
            'location_id' => $this->business->locations()->firstOrFail()->id,
            'type' => $type,
            'status' => $type == 'sell' ? 'final' : 'received',
            'payment_status' => 'due',
            'contact_id' => $contact->id,
            'invoice_no' => 'CHQ-TEST-'.uniqid(),
            'ref_no' => 'CHQ-TEST-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => $total,
            'final_total' => $total,
            'created_by' => $this->user->id,
        ]);
    }

    private function payWithCheque($transaction, $amount, $due_date)
    {
        app(TransactionUtil::class)->createOrUpdatePaymentLines($transaction, [[
            'amount' => $amount,
            'method' => 'cheque',
            'cheque_number' => '778899',
            'cheque_bank_name' => 'Bank Misr',
            'cheque_due_date' => $due_date,
            'paid_on' => now()->toDateTimeString(),
        ]], $this->business->id, $this->user->id, false);

        return app(TransactionUtil::class)->updatePaymentStatus($transaction->id, $transaction->final_total);
    }

    public function test_sell_cheque_goes_to_register_and_is_not_a_payment(): void
    {
        $sell = $this->makeTransaction('sell', 1000);
        $status = $this->payWithCheque($sell, 1000, now()->addDays(60)->format('Y-m-d'));

        $this->assertSame('due', $status);
        $this->assertSame(0, TransactionPayment::where('transaction_id', $sell->id)->count());

        $cheque = Cheque::where('transaction_id', $sell->id)->firstOrFail();
        $this->assertSame('receivable', $cheque->type);
        $this->assertSame('pending', $cheque->status);
        $this->assertSame('Bank Misr', $cheque->bank_name);
        $this->assertSame(now()->addDays(60)->format('Y-m-d'), $cheque->due_date->format('Y-m-d'));
        $this->assertEquals(1000, $cheque->amount);
    }

    public function test_collecting_a_cheque_creates_the_payment_and_marks_invoice_paid(): void
    {
        $sell = $this->makeTransaction('sell', 1000);
        $this->payWithCheque($sell, 1000, now()->format('Y-m-d'));
        $cheque = Cheque::where('transaction_id', $sell->id)->firstOrFail();

        app(ChequeUtil::class)->collect($this->business->id, $cheque->id, now()->toDateTimeString());

        $cheque->refresh();
        $this->assertSame('collected', $cheque->status);
        $payment = TransactionPayment::findOrFail($cheque->payment_id);
        $this->assertEquals(1000, $payment->amount);
        $this->assertSame('cheque', $payment->method);
        $this->assertSame('778899', $payment->cheque_number);
        $this->assertSame('paid', $sell->fresh()->payment_status);

        $this->expectException(\Exception::class);
        app(ChequeUtil::class)->collect($this->business->id, $cheque->id, now()->toDateTimeString());
    }

    public function test_collecting_more_than_invoice_due_pays_the_rest_on_account(): void
    {
        $sell = $this->makeTransaction('sell', 1000);
        $this->payWithCheque($sell, 1000, now()->format('Y-m-d'));
        app(TransactionUtil::class)->createOrUpdatePaymentLines($sell, [[
            'amount' => 600, 'method' => 'cash', 'paid_on' => now()->toDateTimeString(),
        ]], $this->business->id, $this->user->id, false);
        $cheque = Cheque::where('transaction_id', $sell->id)->firstOrFail();

        app(ChequeUtil::class)->collect($this->business->id, $cheque->id, now()->toDateTimeString());

        $this->assertEquals(400, TransactionPayment::findOrFail($cheque->fresh()->payment_id)->amount);
        $this->assertSame('paid', $sell->fresh()->payment_status);
        $this->assertTrue(TransactionPayment::whereNull('transaction_id')
            ->where('payment_for', $sell->contact_id)->where('method', 'cheque')
            ->where('amount', 600)->exists());
    }

    public function test_bounced_cheque_leaves_invoice_due(): void
    {
        $sell = $this->makeTransaction('sell', 500);
        $this->payWithCheque($sell, 500, now()->addDays(10)->format('Y-m-d'));
        $cheque = Cheque::where('transaction_id', $sell->id)->firstOrFail();

        app(ChequeUtil::class)->changeStatus($this->business->id, $cheque->id, Cheque::STATUS_BOUNCED, null, 'insufficient funds');

        $this->assertSame('bounced', $cheque->fresh()->status);
        $this->assertSame(0, TransactionPayment::where('transaction_id', $sell->id)->count());
        $this->assertSame('due', $sell->fresh()->payment_status);
    }

    public function test_purchase_cheque_is_payable_and_clearing_pays_the_purchase(): void
    {
        $purchase = $this->makeTransaction('purchase', 2000);
        $this->payWithCheque($purchase, 2000, now()->addDays(30)->format('Y-m-d'));
        $cheque = Cheque::where('transaction_id', $purchase->id)->firstOrFail();
        $this->assertSame('payable', $cheque->type);

        app(ChequeUtil::class)->collect($this->business->id, $cheque->id, now()->toDateTimeString());

        $this->assertSame('paid', $purchase->fresh()->payment_status);
    }

    public function test_cash_payment_still_creates_payment_directly(): void
    {
        $sell = $this->makeTransaction('sell', 300);
        app(TransactionUtil::class)->createOrUpdatePaymentLines($sell, [[
            'amount' => 300, 'method' => 'cash', 'paid_on' => now()->toDateTimeString(),
        ]], $this->business->id, $this->user->id, false);

        $this->assertSame(1, TransactionPayment::where('transaction_id', $sell->id)->count());
        $this->assertSame(0, Cheque::where('transaction_id', $sell->id)->count());
    }

    public function test_summary_and_cash_flow_buckets(): void
    {
        $sell = $this->makeTransaction('sell', 700);
        $this->payWithCheque($sell, 700, now()->subDays(3)->format('Y-m-d'));
        $purchase = $this->makeTransaction('purchase', 400);
        $this->payWithCheque($purchase, 400, now()->subDays(1)->format('Y-m-d'));

        $before = Cheque::where('business_id', $this->business->id)->where('status', 'pending')->where('due_date', '<', now()->format('Y-m-d'));
        $summary = app(ChequeUtil::class)->summary($this->business->id, 'receivable');
        $this->assertEquals((clone $before)->where('type', 'receivable')->sum('amount'), $summary['overdue']);

        $flow = app(ChequeUtil::class)->cashFlow($this->business->id);
        $this->assertSame('overdue', $flow[0]['key']);
        $this->assertEquals((clone $before)->where('type', 'receivable')->sum('amount'), $flow[0]['in']);
        $this->assertEquals((clone $before)->where('type', 'payable')->sum('amount'), $flow[0]['out']);
    }

    public function test_contact_ledger_and_page_show_pending_cheques(): void
    {
        $sell = $this->makeTransaction('sell', 1000);
        $this->payWithCheque($sell, 1000, now()->addDays(20)->format('Y-m-d'));
        Cheque::where('transaction_id', $sell->id)->update(['cheque_number' => 'LEDGER-CHQ-55']);

        foreach (['format_1', 'format_2', 'format_3', 'format_4'] as $format) {
            $this->get('/contacts/ledger?contact_id='.$sell->contact_id.'&start_date='.now()->subYear()->format('Y-m-d').'&end_date='.now()->format('Y-m-d').'&format='.$format)
                ->assertOk()
                ->assertSee('LEDGER-CHQ-55')
                ->assertSee(__('cheque.uncovered_due'));
        }

        $this->get('/contacts/'.$sell->contact_id)->assertOk()->assertSee(__('cheque.uncovered_due'));
    }

    public function test_report_pages_render(): void
    {
        $sell = $this->makeTransaction('sell', 1000);
        $this->payWithCheque($sell, 1000, now()->addDays(5)->format('Y-m-d'));
        $cheque = Cheque::where('transaction_id', $sell->id)->firstOrFail();

        $this->get('/cheques')->assertOk()->assertSee(__('cheque.cheques_report'));
        $this->getJson('/cheques/data/receivable?draw=1&start=0&length=25', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonStructure(['data']);
        $this->get('/cheques/'.$cheque->id.'/collect')->assertOk();

        $this->post('/cheques/'.$cheque->id.'/bounce', ['action_date' => now()->format('Y-m-d')])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('bounced', $cheque->fresh()->status);
    }
}
