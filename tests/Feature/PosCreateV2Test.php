<?php

namespace Tests\Feature;

use App\Business;
use App\CashRegister;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PosCreateV2Test extends TestCase
{
    use DatabaseTransactions;

    private $user;

    // العناصر اللي pos.js و keyboard_shortcuts بيعتمدوا عليها (لازم تفضل موجودة في شاشة الكاشير الجديدة)
    private $hooks = [
        'id="add_pos_sell_form"', 'id="location_id"', 'id="customer_id"', 'id="search_product"', 'id="pos_table"',
        'id="product_row_count"', 'id="product_list_body"', 'id="suggestion_page"', 'id="discount_type"',
        'id="discount_amount"', 'id="tax_rate_id"', 'id="shipping_charges"', 'id="final_total_input"',
        'id="total_payable"', 'id="pos-cancel"', 'id="payment_rows_div"', 'id="pos_redirect_url"',
        'id="transaction_sub_type"', 'id="recent_transactions_modal"', 'id="view_suspended_sales"',
        '<span class="total_quantity rr-num">', '<span class="rr-num price_total">',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        // شاشة الكاشير بتحتاج وردية مفتوحة
        CashRegister::where('user_id', $this->user->id)->update(['status' => 'close']);
        CashRegister::create([
            'business_id' => $business->id,
            'location_id' => $business->locations()->first()->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);
    }

    public function test_pos_opens_the_new_screen_with_every_hook(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();

        $this->assertStringContainsString('rr-pos-bar', $html);
        $this->assertStringContainsString('rr-page rr-pos', $html);
        // الشريط القديم مش بيتعرض
        $this->assertStringNotContainsString('pos-header', $html);
        foreach ($this->hooks as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        // العميل في الشريط العلوي جنب البحث، والخيارات في نافذة، ومنطقة الـ scroll فيها السطور بس
        $top = strpos($html, 'rr-pos-cart__top');
        $scroll = strpos($html, 'rr-pos-cart__scroll');
        $this->assertTrue($top < strpos($html, 'id="customer_id"') && strpos($html, 'id="customer_id"') < $scroll);
        $this->assertStringNotContainsString('<details class="rr-pos-options"', $html);
        // نافذة "تم البيع" وزرار صوت المسح
        $this->assertStringContainsString('id="rr_pos_done_modal"', $html);
        $this->assertStringContainsString('data-rr-pos-sound', $html);
        $this->assertLessThan(strpos($html, 'id="pos_table"'), $scroll);
        // حماية من حفظ الفاتورة مرتين (Enter مرتين كانت بتعمل فاتورتين بنفس الرقم)
        $this->assertStringContainsString('if (saleXhr) { xhr.abort(); return; }', $html);
        // كل id بيظهر مرة واحدة (الشاشة القديمة كانت بتكرر total_payable و pos-finalize)
        foreach (['id="total_payable"', 'id="final_total_input"', 'id="pos-cancel"', 'id="search_product"'] as $id) {
            $this->assertSame(1, substr_count($html, $id), $id);
        }
    }

    public function test_shortcuts_come_from_the_screen_not_the_business_settings(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();

        // نافذة الاختصارات (F1) فيها المجموعات والمفاتيح
        $this->assertStringContainsString('id="rr_pos_keys_modal"', $html);
        $this->assertStringContainsString('data-target="#rr_pos_keys_modal"', $html);
        foreach (['F1', 'F2', 'F3', 'F4', 'F8', 'F9', 'F10', 'Delete'] as $key) {
            $this->assertStringContainsString('<kbd class="rr-pos-keys__kbd">'.$key.'</kbd>', $html, $key);
        }
        // اختصارات إعدادات النشاط (Mousetrap) كانت بتتعارض مع F2 و F4 فمش بتتحمّل هنا
        $this->assertStringNotContainsString('Mousetrap.bind(', $html);
    }

    public function test_new_product_window_is_raqmi_only_on_the_new_screen(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();
        $this->assertStringContainsString('data-href="'.url('/products/quick_add').'?v2=1&product_for=pos&_"', $html);

        // pos.js بيزوّد "?product_for=pos" على الرابط
        $v2 = $this->get('/products/quick_add?v2=1&product_for=pos&_?product_for=pos')->assertOk()->getContent();
        $this->assertStringContainsString('rr-qp', $v2);
        // saveQuickProduct و product.js بيعتمدوا على الأسماء والـ ids دي
        foreach (['id="quick_add_product_form"', 'id="single_dpp"', 'id="single_dpp_inc_tax"', 'id="profit_percent"', 'id="single_dsp"',
            'id="single_dsp_inc_tax"', 'id="tax_type"', 'name="enable_stock"', 'name="opening_stock[', 'id="product_locations"', 'name="type"'] as $hook) {
            $this->assertStringContainsString($hook, $v2, $hook);
        }
        // من غير محرر tinymce (بيدور على textarea#product_description)
        $this->assertStringNotContainsString('id="product_description"', $v2);

        // الشراء والكاشير القديم على النافذة القديمة
        $this->get('/products/quick_add?product_for=pos')->assertOk()->assertDontSee('rr-qp', false);
    }

    public function test_new_customer_window_keeps_the_pos_js_hooks(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();

        $this->assertStringContainsString('rr-qc', $html);
        foreach (['id="quick_add_contact"', 'id="contact_type" value="customer"', 'name="contact_type_radio" value="individual" checked',
            'id="first_name"', 'id="supplier_business_name"', 'id="mobile"', 'id="tax_number"', 'id="contact_id"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        // shipping_address و pay_term_number كانوا بيتكرروا مع فورم الفاتورة
        $this->assertSame(1, substr_count($html, 'id="shipping_address"'));
    }

    public function test_payment_window_is_raqmi_and_keeps_the_pos_js_hooks(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();

        $this->assertStringContainsString('rr-pay', $html);
        foreach (['id="payment_rows_div"', 'id="payment_row_index"', 'id="add-payment-row"', 'id="change_return_payment_data"',
            'class="rr-num total_payable_span"', 'class="rr-num total_paying"', 'class="rr-num balance_due"', 'class="rr-num change_return_span"',
            'id="total_paying_input"', 'id="in_balance_due"', 'id="advance_balance"', 'id="pos-save"', 'id="card_details_modal"',
            'id="card_number"', 'id="card_transaction_number"', 'id="card_security"', 'id="pos-save-card"', 'id="amount_0"', 'id="method_0"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        $this->assertStringNotContainsString('id="modal_payment" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"', $html);

        // سطر الدفع الإضافي: بتصميم رقمي لما الطلب فيه v2=1 والقديم من غيره
        $location = Business::findOrFail($this->user->business_id)->locations()->first();
        $row = $this->post('/sells/pos/get_payment_row', ['row_index' => 1, 'location_id' => $location->id, 'v2' => 1], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringContainsString('payment_row rr-pay__row', $row);
        $this->assertStringContainsString('remove_payment_row', $row);
        $this->assertStringContainsString('id="amount_1"', $row);
        $old = $this->post('/sells/pos/get_payment_row', ['row_index' => 1, 'location_id' => $location->id], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringNotContainsString('rr-pay__row', $old);
    }

    public function test_cash_button_opens_the_cash_window_and_keeps_the_express_button(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();

        if (! str_contains($html, 'data-rr-pos-cash ')) {
            $this->markTestSkipped('الدفع النقدي السريع مقفول في إعدادات نقطة البيع لهذا النشاط');
        }
        $this->assertStringContainsString('id="rr_pos_cash_modal"', $html);
        $this->assertMatchesRegularExpression('/class="pos-express-finalize hide" data-pay_method="cash" data-rr-pos-cash-submit/', $html);
    }

    public function test_edit_opens_the_new_screen_with_the_invoice_lines(): void
    {
        // أحدث فاتورة كاشير ليها أصناف ومفيش عليها مرتجع
        $sale = \App\Transaction::where('business_id', $this->user->business_id)
            ->where('type', 'sell')->where('is_direct_sale', 0)->whereNull('sub_type')
            ->whereHas('sell_lines')
            ->whereDoesntHave('return_parent')
            ->where(fn ($q) => $q->whereNull('zatca_status')->orWhere('zatca_status', '!=', 'success'))
            ->latest('id')->first();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة كاشير للتجربة');
        }
        \App\Transaction::whereKey($sale->id)->update(['transaction_date' => now()]);

        $html = $this->get('/pos/'.$sale->id.'/edit')->assertOk()->getContent();

        $this->assertStringContainsString('rr-page rr-pos', $html);
        $this->assertStringContainsString('id="edit_pos_sell_form"', $html);
        $this->assertStringNotContainsString('id="add_pos_sell_form"', $html);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertStringContainsString('<span class="rr-code">'.$sale->invoice_no.'</span>', $html);
        $lines = $sale->sell_lines()->whereNull('parent_sell_line_id')->count();
        $this->assertSame($lines, substr_count($html, 'class="product_row'));
        $this->assertStringContainsString('id="product_row_count" value="'.$lines.'"', $html);
        $this->assertSame(1, substr_count($html, 'id="total_payable"'));
    }

    public function test_suspended_sales_modal_lists_each_sale_with_resume_and_delete(): void
    {
        $sale = \App\Transaction::where('business_id', $this->user->business_id)
            ->where('type', 'sell')->where('is_direct_sale', 0)->whereNull('sub_type')->where('status', 'final')
            ->latest('id')->first();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة كاشير للتجربة');
        }
        \App\Transaction::where('business_id', $this->user->business_id)->where('is_suspend', 1)->update(['is_suspend' => 0]);
        \App\Transaction::whereKey($sale->id)->update(['is_suspend' => 1, 'additional_notes' => 'ترابيزة 5']);

        $html = $this->get('/sells?suspended=1', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString('rr-susp', $html);
        $this->assertSame(1, substr_count($html, 'data-rr-suspended-item data-search'));
        $this->assertStringContainsString('ترابيزة 5', $html);
        $this->assertStringContainsString('/pos/'.$sale->id.'/edit', $html);
        // app.js بيعتمد على الكلاسين دول في الحذف
        $this->assertMatchesRegularExpression('/class="[^"]*delete-sale is_suspended"/', $html);
    }

    public function test_recent_transactions_list_each_sale_with_edit_print_and_delete(): void
    {
        $html = $this->get('/pos/create')->assertOk()->getContent();
        // pos.js بيحمّل التبويبات في الـ divs دي من روابط data-toggle="tab"
        foreach (['final', 'quotation', 'draft'] as $tab) {
            $this->assertStringContainsString('href="#tab_'.$tab.'" data-toggle="tab"', $html);
            $this->assertStringContainsString('id="tab_'.$tab.'"', $html);
        }

        $sale = \App\Transaction::where('business_id', $this->user->business_id)
            ->where('type', 'sell')->where('is_direct_sale', 0)->whereNull('sub_type')
            ->latest('id')->first();
        if (! $sale) {
            $this->markTestSkipped('لا توجد فاتورة كاشير للتجربة');
        }
        \App\Transaction::whereKey($sale->id)->update(['created_by' => $this->user->id, 'status' => 'final', 'created_at' => now()->addMinute()]);

        $list = $this->get('/sells/pos/get-recent-transactions?status=final', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString('rr-recent__item--final', $list);
        $this->assertStringContainsString('<span class="rr-code rr-susp__no">'.$sale->invoice_no.'</span>', $list);
        $this->assertStringContainsString('/pos/'.$sale->id.'/edit', $list);
        // pos.js و app.js بيعتمدوا على الكلاسات دي في الطباعة والحذف
        $this->assertMatchesRegularExpression('/class="[^"]*print-invoice-link"/', $list);
        $this->assertMatchesRegularExpression('/class="[^"]*delete-sale"/', $list);
        $this->assertLessThanOrEqual(config('constants.pos_recent_transactions_display_limit', 10), substr_count($list, 'data-rr-recent-item'));

        $empty = $this->get('/sells/pos/get-recent-transactions?status=draft&transaction_sub_type=none', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringContainsString('rr-empty', $empty);
    }

    public function test_module_sub_type_keeps_the_classic_screen(): void
    {
        $this->get('/pos/create?sub_type=repair')->assertOk()
            ->assertDontSee('rr-page rr-pos', false)
            ->assertSee('pos-header', false);
    }

    public function test_product_suggestions_show_an_initial_when_there_is_no_image(): void
    {
        $location = Business::findOrFail($this->user->business_id)->locations()->first();
        $html = $this->get('/sells/pos/get-product-suggestion?location_id='.$location->id.'&page=1', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        if (! str_contains($html, 'product_box')) {
            $this->markTestSkipped('لا توجد أصناف في هذا الفرع');
        }
        // كل كارت فيه أول حرف من الاسم (بيظهر لو مفيش صورة أو ملف الصورة مش موجود)
        $this->assertSame(substr_count($html, 'class="product_box '), substr_count($html, 'rr-pos-initial'));
        // والكروت اللي ليها صورة فيها img بيحوّل الكارت للحرف لو الملف مش موجود
        $withImage = substr_count($html, "onerror=\"this.parentNode.classList.add('image-container--empty')\"");
        $withoutImage = substr_count($html, 'image-container--empty') - $withImage;
        $this->assertSame(substr_count($html, 'class="product_box '), $withImage + $withoutImage);
    }
}
