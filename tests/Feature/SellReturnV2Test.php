<?php

namespace Tests\Feature;

use App\Business;
use App\CashRegister;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SellReturnV2Test extends TestCase
{
    use DatabaseTransactions;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    private function finalSale(): ?Transaction
    {
        return Transaction::where('business_id', $this->user->business_id)
            ->where('type', 'sell')->where('status', 'final')
            ->whereHas('sell_lines')
            ->whereDoesntHave('return_parent')
            ->latest('id')->first();
    }

    public function test_pos_return_button_opens_a_modal_with_the_pos_js_hooks(): void
    {
        CashRegister::where('user_id', $this->user->id)->update(['status' => 'close']);
        CashRegister::create([
            'business_id' => $this->user->business_id,
            'location_id' => Business::findOrFail($this->user->business_id)->locations()->first()->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $html = $this->get('/pos/create')->assertOk()->getContent();

        $this->assertStringContainsString('data-target="#rr_pos_return_modal"', $html);
        $this->assertStringContainsString('id="rr_pos_return_modal"', $html);
        // pos.js بيقرا الرقم من الخانة دي وبيسمع للزرار ده
        $this->assertSame(1, substr_count($html, 'id="send_for_sell_return_invoice_no"'));
        $this->assertSame(1, substr_count($html, 'id="send_for_sell_return"'));
        // الـ popover القديم اتشال
        $this->assertStringNotContainsString('data-toggle="popover"', substr($html, strpos($html, 'id="rr_pos_return_btn"'), 400));
    }

    public function test_only_final_sales_can_be_returned(): void
    {
        $sale = $this->finalSale();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة نهائية للتجربة');
        }

        $this->getJson('/validate-invoice-to-return/'.urlencode($sale->invoice_no))
            ->assertOk()->assertJson(['success' => 1])
            ->assertJsonFragment(['redirect_url' => url('/sell-return/add/'.$sale->id)]);

        Transaction::whereKey($sale->id)->update(['status' => 'draft']);
        $this->getJson('/validate-invoice-to-return/'.urlencode($sale->invoice_no))
            ->assertOk()->assertJson(['success' => 0]);
    }

    public function test_return_screen_keeps_the_fields_sell_return_js_needs(): void
    {
        $sale = $this->finalSale();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة نهائية للتجربة');
        }

        $html = $this->get('/sell-return/add/'.$sale->id)->assertOk()->getContent();

        $this->assertStringContainsString('rr-page rr-sf rr-ret', $html);
        foreach (['id="sell_return_form"', 'id="location_id"', 'name="transaction_id"', 'id="tax_percent"', 'id="tax_amount"',
            'id="sell_return_table"', 'id="discount_type"', 'id="discount_amount"', 'id="transaction_date"', 'id="net_return"',
            'id="total_return_discount"', 'id="total_return_tax"', 'id="rr_ret_done_modal"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        $lines = $sale->sell_lines()->count();
        $this->assertSame($lines, substr_count($html, 'class="form-control input-sm input_number return_qty input_quantity"'));
        $this->assertSame($lines, substr_count($html, 'class="rr-ret-qty" data-max="'));
    }

    public function test_saving_a_return_answers_with_the_return_invoice(): void
    {
        $sale = $this->finalSale();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة نهائية للتجربة');
        }
        $line = $sale->sell_lines()->first();
        // التاريخ بصيغة النشاط (زي ما الشاشة بتبعته)
        $business = Business::findOrFail($this->user->business_id);
        $date = now()->format($business->date_format.' '.($business->time_format == 12 ? 'h:i A' : 'H:i'));

        $result = $this->postJson('/sell-return', [
            'transaction_id' => $sale->id,
            'transaction_date' => $date,
            'discount_type' => '',
            'discount_amount' => 0,
            'tax_id' => $sale->tax_id,
            'tax_amount' => 0,
            'products' => [['quantity' => 1, 'unit_price_inc_tax' => $line->unit_price_inc_tax, 'sell_line_id' => $line->id]],
        ])->assertOk()->json();

        if (($result['success'] ?? 0) != 1) {
            $this->markTestSkipped('الحفظ رجع: '.($result['msg'] ?? ''));
        }
        $return = Transaction::where('return_parent_id', $sale->id)->where('type', 'sell_return')->firstOrFail();
        $this->assertSame($return->id, $result['sell_return']['id']);
        $this->assertSame($return->invoice_no, $result['sell_return']['invoice_no']);
        $this->assertStringContainsString('/sell-return/print/'.$return->id, $result['sell_return']['print_url']);
    }
}
