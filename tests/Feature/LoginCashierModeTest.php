<?php

namespace Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginCashierModeTest extends TestCase
{
    use DatabaseTransactions;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->where('is_active', 1)->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->user->forceFill(['password' => Hash::make('test-secret'), 'status' => 'active', 'allow_login' => 1])->save();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function test_login_page_renders()
    {
        $this->get('/login')->assertOk()->assertSee('open_pos', false);
    }

    public function test_cashier_mode_opens_pos_after_login()
    {
        $this->post('/login', ['username' => $this->user->username, 'password' => 'test-secret', 'open_pos' => '1'])
            ->assertRedirect('/pos/create');
    }

    public function test_login_without_cashier_mode_goes_home()
    {
        $this->post('/login', ['username' => $this->user->username, 'password' => 'test-secret'])
            ->assertRedirect('/home');
    }
}
