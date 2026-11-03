<?php

namespace Tests\Feature;

use App\Business;
use App\CashRegister;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShiftCloseV2Test extends TestCase
{
    use DatabaseTransactions;

    private $user;

    private $register;

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        CashRegister::where('user_id', $this->user->id)->update(['status' => 'close']);
        $this->register = CashRegister::create([
            'business_id' => $business->id,
            'location_id' => $business->locations()->first()->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);
        // رصيد افتتاح 100 + بيع نقدي 50 + مصروف نقدي 20 = المتوقع 130
        $this->register->cash_register_transactions()->createMany([
            ['amount' => 100, 'pay_method' => 'cash', 'type' => 'credit', 'transaction_type' => 'initial'],
            ['amount' => 50, 'pay_method' => 'cash', 'type' => 'credit', 'transaction_type' => 'sell'],
            ['amount' => 20, 'pay_method' => 'cash', 'type' => 'debit', 'transaction_type' => 'expense'],
        ]);
    }

    public function test_close_window_shows_the_expected_cash_and_keeps_the_form_fields(): void
    {
        $html = $this->get('/cash-register/close-register', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString('rr-shift', $html);
        foreach (['name="user_id"', 'name="closing_amount"', 'name="total_card_slips"', 'name="total_cheques"', 'name="closing_note"', 'data-rr-shift-diff'] as $field) {
            $this->assertStringContainsString($field, $html, $field);
        }
        $this->assertStringContainsString('data-rr-shift-expected="130', $html);
        $this->assertStringContainsString(__('shift_close.expected'), $html);
        // البطاقة مالهاش حركة فمش بتظهر في طرق الدفع
        $this->assertStringNotContainsString('<th scope="row">'.__('cash_register.card_payment').'</th>', $html);
    }

    public function test_only_the_top_10_products_are_listed_and_the_rest_is_one_line(): void
    {
        $register = (object) ['user_id' => $this->user->id, 'open_time' => now()->subHour()->format('Y-m-d H:i:s'), 'user_name' => 'x', 'location_name' => 'y',
            'cash_in_hand' => 0, 'total_cash' => 0, 'total_cash_refund' => 0, 'total_cash_expense' => 0, 'total_sale' => 0, 'total_refund' => 0,
            'total_expense' => 0, 'total_card_slips' => 0, 'total_cheques' => 0];
        // طلب عادي الأول عشان SetSessionData يملا الجلسة (العملة وصيغة التاريخ) اللي النافذة بتحتاجها
        $this->get('/cash-register/close-register', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        // 12 صنف: الصنف رقم i قيمته i*10، فأقل اتنين (10 و 20) يتجمعوا في سطر "و2 صنف آخر"
        $products = collect(range(1, 12))->map(fn ($i) => (object) ['product_name' => 'P'.$i, 'type' => 'single', 'sku' => 'S'.$i, 'total_quantity' => 1, 'total_amount' => $i * 10]);
        $html = view('cash_register.close_register_modal', [
            'register_details' => $register, 'payment_types' => [], 'pos_settings' => [],
            'details' => ['transaction_details' => (object) ['total_sales' => 780, 'total_invoices' => 12], 'product_details' => $products,
                'product_details_by_brand' => collect(), 'types_of_service_details' => null],
        ])->render();

        $this->assertSame(10, substr_count($html, '<span class="rr-code rr-shift__muted">S'));
        $this->assertStringContainsString('>P12 <span', $html);
        $this->assertStringNotContainsString('>P1 <span', $html);
        $this->assertStringNotContainsString('>P2 <span', $html);
        $this->assertStringContainsString(__('shift_close.more_products', ['count' => 2]), $html);
        $this->assertStringContainsString(__('shift_close.top_products'), $html);
    }

    public function test_details_of_the_open_shift_show_the_summary_until_now(): void
    {
        $html = $this->get('/cash-register/register-details', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString(__('shift_close.details_title'), $html);
        $this->assertStringContainsString(__('shift_close.status_open'), $html);
        $this->assertStringContainsString('data-rr-shift-print', $html);
        $this->assertStringContainsString(__('shift_close.expected'), $html);
        // الوردية لسه مفتوحة: مفيش معدود ولا بيانات إغلاق
        $this->assertStringNotContainsString('data-rr-shift-closing>', $html);
        $this->assertStringNotContainsString(__('shift_close.counted'), $html);
    }

    public function test_details_of_a_closed_shift_show_the_counted_cash_and_the_shortage(): void
    {
        $this->register->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 125, 'total_card_slips' => 2,
            'total_cheques' => 1, 'closing_note' => 'ملاحظة الإغلاق', 'denominations' => ['100' => 1, '25' => 1, '5' => 0]]);

        $html = $this->get('/cash-register/'.$this->register->id, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString(__('shift_close.status_closed'), $html);
        $this->assertStringContainsString('data-rr-shift-closing>', $html);
        $this->assertStringContainsString(__('shift_close.counted'), $html);
        $this->assertStringContainsString('rr-badge--danger" data-rr-shift-diff-static', $html); // 125 من 130 = عجز
        $this->assertStringContainsString('ملاحظة الإغلاق', $html);
        $this->assertStringContainsString('<th scope="row" class="rr-num">100</th>', $html);
        $this->assertStringNotContainsString('<th scope="row" class="rr-num">5</th>', $html); // فئة عددها صفر مش بتظهر
    }

    public function test_details_list_all_products_with_a_search_box(): void
    {
        $this->get('/cash-register/register-details', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $register = (object) ['user_id' => $this->user->id, 'open_time' => now()->subHour()->format('Y-m-d H:i:s'), 'closed_at' => null, 'register_status' => 'open',
            'user_name' => 'x', 'location_name' => 'y', 'cash_in_hand' => 0, 'total_cash' => 0, 'total_cash_refund' => 0, 'total_cash_expense' => 0,
            'total_sale' => 0, 'total_refund' => 0, 'total_expense' => 0, 'denominations' => null, 'closing_note' => null];
        $products = collect(range(1, 12))->map(fn ($i) => (object) ['product_name' => 'P'.$i, 'type' => 'single', 'sku' => 'S'.$i, 'total_quantity' => 1, 'total_amount' => $i * 10]);
        $html = view('cash_register.register_details', [
            'register_details' => $register, 'payment_types' => [], 'close_time' => now()->format('Y-m-d H:i:s'),
            'details' => ['transaction_details' => (object) ['total_sales' => 780, 'total_invoices' => 12], 'product_details' => $products,
                'product_details_by_brand' => collect(), 'types_of_service_details' => null],
        ])->render();

        $this->assertSame(12, substr_count($html, '<span class="rr-code rr-shift__muted">S'));
        $this->assertStringContainsString('data-rr-shift-search', $html);
        $this->assertStringNotContainsString('data-rr-shift-rest', $html);
        $this->assertStringContainsString('data-rr-shift-print-btn="full"', $html);
    }

    public function test_closing_saves_the_counted_cash(): void
    {
        $this->from('/pos/create')->post('/cash-register/close-register', [
            'user_id' => $this->user->id,
            'closing_amount' => '125',
            'total_card_slips' => 0,
            'total_cheques' => 0,
            'closing_note' => 'عجز 5',
        ])->assertRedirect('/pos/create');

        $this->register->refresh();
        $this->assertSame('close', $this->register->status);
        $this->assertEquals(125, (float) $this->register->closing_amount);
        $this->assertSame('عجز 5', $this->register->closing_note);
    }
}
