<?php

namespace Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SellCreateV2Test extends TestCase
{
    use DatabaseTransactions;

    private $user;

    // العناصر اللي pos.js بيعتمد عليها (لازم تفضل موجودة في الشاشة الجديدة)
    private $hooks = [
        'id="add_sell_form"', 'id="location_id"', 'id="customer_id"', 'id="transaction_date"', 'id="search_product"',
        'id="pos_table"', 'id="product_row_count"', 'id="discount_type"', 'id="discount_amount"', 'id="tax_rate_id"',
        'id="shipping_charges"', 'id="final_total_input"', 'id="total_payable"', 'id="submit-sell"', 'id="save-and-print"',
        'id="is_save_and_print"', '<span class="total_quantity">', '<span class="price_total">',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function test_create_opens_the_new_screen_with_every_form_hook(): void
    {
        $this->user->forceFill(['use_classic_sell_create' => 0])->save();

        foreach (['', '?status=quotation', '?sale_type=sales_order'] as $query) {
            $html = $this->get('/sells/create'.$query)->assertOk()->getContent();
            $this->assertStringContainsString('rr-sf-summary', $html, $query);
            foreach ($this->hooks as $hook) {
                $this->assertStringContainsString($hook, $html, $query.' '.$hook);
            }
        }

        // الدفع بيظهر للفاتورة العادية بس، زي الشاشة القديمة
        $this->get('/sells/create')->assertSee('id="payment_rows_div"', false);
        $this->get('/sells/create?status=quotation')->assertDontSee('id="payment_rows_div"', false);
    }

    public function test_each_user_can_switch_back_and_keeps_the_document_type(): void
    {
        $this->user->forceFill(['use_classic_sell_create' => 0])->save();

        $this->post(route('sells.create.switch'), ['version' => 'classic', 'query' => 'status=quotation&x=1'])
            ->assertRedirect(action([\App\Http\Controllers\SellController::class, 'create']).'?status=quotation');
        $this->assertTrue((bool) $this->user->fresh()->use_classic_sell_create);

        $this->get('/sells/create')->assertOk()
            ->assertDontSee('rr-sf-summary', false)
            ->assertSee(__('sell_form.try_new_screen'));

        $this->post(route('sells.create.switch'), ['version' => 'new'])
            ->assertRedirect(action([\App\Http\Controllers\SellController::class, 'create']));
        $this->assertFalse((bool) $this->user->fresh()->use_classic_sell_create);
        $this->post(route('sells.create.switch'), ['version' => 'other'])->assertSessionHasErrors('version');
    }

    public function test_edit_opens_the_new_screen_and_switching_returns_to_the_same_invoice(): void
    {
        $sell = \App\Transaction::where('business_id', $this->user->business_id)->where('type', 'sell')
            ->where('status', 'final')->whereHas('sell_lines')->latest('id')->firstOrFail();
        $this->user->forceFill(['use_classic_sell_create' => 0])->save();

        $html = $this->get(action([\App\Http\Controllers\SellController::class, 'edit'], [$sell->id]))->assertOk()->getContent();
        foreach (['id="edit_sell_form"', 'id="payment_rows_div"', 'id="customer_id"', 'id="final_total_input"', 'id="submit-sell"', 'id="rr_sf_cust"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        // payment_rows_div مرة واحدة بس (الشاشة القديمة كانت بتكررها مع كل دفعة)
        $this->assertSame(1, substr_count($html, 'id="payment_rows_div"'));

        $this->post(route('sells.create.switch'), ['version' => 'classic', 'edit_id' => $sell->id])
            ->assertRedirect(action([\App\Http\Controllers\SellController::class, 'edit'], [$sell->id]));
        $this->get(action([\App\Http\Controllers\SellController::class, 'edit'], [$sell->id]))->assertOk()
            ->assertDontSee('rr-sf-summary', false)
            ->assertSee(__('sell_form.try_new_screen'));
    }

    public function test_customer_summary_returns_credit_details_for_this_business_only(): void
    {
        $contact = \App\Contact::where('business_id', $this->user->business_id)->whereIn('type', ['customer', 'both'])->firstOrFail();

        $this->getJson(route('sells.customer_summary', [$contact->id]))->assertOk()
            ->assertJsonStructure(['name', 'mobile', 'due', 'due_text', 'credit_limit', 'credit_available', 'last_invoice', 'url']);

        $other = \App\Contact::where('business_id', '!=', $this->user->business_id)->firstOrFail();
        $this->getJson(route('sells.customer_summary', [$other->id]))->assertNotFound();
    }
}
