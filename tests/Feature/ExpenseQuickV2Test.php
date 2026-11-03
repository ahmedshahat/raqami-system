<?php

namespace Tests\Feature;

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExpenseQuickV2Test extends TestCase
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

    public function test_pos_expense_window_keeps_the_fields_pos_js_needs(): void
    {
        $location = $this->business->locations()->first();
        $html = $this->get('/expenses/create?location_id='.$location->id, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringContainsString('rr-exp', $html);
        foreach (['id="add_expense_modal_form"', 'id="expense_final_total"', 'name="expense_category_id"', 'id="expense_sub_category_id"', 'name="location_id"',
            'id="expense_transaction_date"', 'name="payment[0][amount]"', 'class="form-control payment-amount input_number"', 'payment_types_dropdown',
            'name="payment[0][paid_on]"', 'id="expense_payment_due"', 'name="additional_notes"', 'name="tax_id"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringContainsString(__('expense_quick.title'), $html);
        // الفرع معروف من الكاشير فبيتعلّم عليه
        $this->assertMatchesRegularExpression('/<option value="'.$location->id.'"[^>]*selected/', $html);
    }

    public function test_saving_from_the_window_creates_the_expense_with_its_payment(): void
    {
        $location = $this->business->locations()->first();
        $this->get('/expenses/create', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $format = $this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i');

        $this->post('/expenses', [
            'location_id' => $location->id,
            'final_total' => '75',
            'transaction_date' => now()->format($format),
            'additional_notes' => 'كهرباء المحل',
            'tax_id' => '',
            'payment' => [['amount' => '75', 'method' => 'cash', 'paid_on' => now()->format($format), 'note' => '']],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJson(['success' => 1]);

        $expense = Transaction::where('business_id', $this->business->id)->where('type', 'expense')->latest('id')->first();
        $this->assertSame('كهرباء المحل', $expense->additional_notes);
        $this->assertEquals(75, (float) $expense->final_total);
        $this->assertSame('paid', $expense->payment_status);
    }
}
