<?php

namespace Tests\Feature;

use App\Business;
use App\CashRegister;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerDisplayV2Test extends TestCase
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

    public function test_customer_display_is_a_standalone_raqmi_page(): void
    {
        $html = $this->get('/customer-display')->assertOk()->getContent();

        $this->assertStringContainsString('class="rr-page rr-cd"', $html);
        $this->assertStringContainsString('data-rr-cd-list', $html);
        $this->assertStringContainsString("'rr_pos_display'", $html);
        $this->assertStringContainsString(__('customer_display.total'), $html);
        $this->assertStringContainsString(e($this->business->name), $html);
        // من غير القائمة الجانبية ولا شريط التطبيق
        $this->assertStringNotContainsString('rr-appbar', $html);
        $this->assertStringNotContainsString('main-sidebar', $html);
    }

    public function test_pos_sends_the_cart_only_when_the_display_is_enabled(): void
    {
        CashRegister::where('user_id', $this->user->id)->update(['status' => 'close']);
        CashRegister::create(['business_id' => $this->business->id, 'location_id' => $this->business->locations()->first()->id,
            'user_id' => $this->user->id, 'status' => 'open']);
        $settings = json_decode($this->business->pos_settings ?: '{}', true) ?: [];

        $this->business->update(['pos_settings' => json_encode(array_merge($settings, ['customer_display_screen' => 1]))]);
        $html = $this->get('/pos/create')->assertOk()->getContent();
        $this->assertStringContainsString("KEY = 'rr_pos_display'", $html);

        $this->business->update(['pos_settings' => json_encode(array_merge($settings, ['customer_display_screen' => 0]))]);
        $html = $this->get('/pos/create')->assertOk()->getContent();
        $this->assertStringNotContainsString("KEY = 'rr_pos_display'", $html);
    }
}
