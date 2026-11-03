<?php

namespace Tests\Feature;

use App\Business;
use App\User;
use App\Utils\HomeDashboardUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($business->owner_id);
        $this->user->dashboard_layout = null;
        $this->user->theme_mode = null;
        $this->user->use_classic_home = false;
        $this->user->save();
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function test_home_v2_renders_for_every_period()
    {
        foreach (['today', '7_days', 'this_month', 'last_month', 'this_fy'] as $period) {
            $this->get('/home/v2?period=' . $period)
                ->assertOk()
                ->assertSee('rr-kpis', false)
                ->assertSee('rr-customize', false);
        }

        $this->get('/home/v2?period=custom&start=2026-01-01&end=2026-03-31')
            ->assertOk()
            // كارت المبيعات بيفتح قايمة الفواتير على نفس الفترة
            ->assertSee('/sells?rr_from=2026-01-01&amp;rr_to=2026-03-31', false);
    }

    public function test_saved_layout_hides_and_reorders_sections()
    {
        $this->post('/home/v2/layout', [
            'order' => [
                'top' => ['kpis', 'secondary'],
                'main' => ['chart', 'attention'],
                'cards' => ['payment_methods', 'top_products', 'aging', 'forecast'],
                'bottom' => ['heatmap', 'modules'],
            ],
            'visible' => ['kpis', 'secondary', 'chart', 'top_products', 'forecast', 'payment_methods', 'attention', 'aging', 'modules'],
        ])->assertRedirect();

        $layout = json_decode($this->user->fresh()->dashboard_layout, true);
        $this->assertSame(['heatmap'], $layout['hidden']);
        $this->assertSame(['payment_methods', 'top_products', 'aging', 'forecast'], $layout['order']['cards']);

        $html = $this->get('/home/v2')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="rr-heat-title"', $html);
        $this->assertLessThan(strpos($html, 'id="rr-top-title"'), strpos($html, 'id="rr-methods-title"'));
    }

    public function test_reset_restores_default_layout()
    {
        $this->user->dashboard_layout = json_encode(['order' => [], 'hidden' => ['chart']]);
        $this->user->save();

        $this->post('/home/v2/layout', ['reset' => 1])->assertRedirect();

        $this->assertNull($this->user->fresh()->dashboard_layout);
    }

    public function test_home_serves_the_new_dashboard_and_a_user_can_switch_back_to_the_classic_one()
    {
        // الجديدة هي الافتراضية على /home
        $this->get('/home')->assertOk()->assertSee('rr-home', false)->assertSee(route('home.switch'), false);

        $this->post('/home/switch', ['version' => 'classic'])->assertRedirect(route('home'));
        $this->assertTrue((bool) $this->user->fresh()->use_classic_home);
        $this->get('/home')->assertOk()->assertSee('rr-classic-note', false)->assertDontSee('rr-home__head', false);

        $this->post('/home/switch', ['version' => 'new'])->assertRedirect(route('home'));
        $this->assertFalse((bool) $this->user->fresh()->use_classic_home);
        $this->get('/home')->assertOk()->assertSee('rr-home__head', false);
    }

    public function test_theme_toggle_applies_to_the_current_user_only()
    {
        $this->user->business->theme_color = 'primary';
        $this->user->business->save();
        $other = User::where('business_id', $this->user->business_id)->where('id', '!=', $this->user->id)->first();

        $this->post('/user/theme-mode', ['mode' => 'dark'])->assertRedirect();

        $this->assertSame('dark', $this->user->fresh()->theme_mode);
        if ($other) {
            $this->assertNull($other->fresh()->theme_mode);
        }
        // مظهر اليوزر بيغلب مظهر النشاط (فاتح)، والزرار بيعرض التبديل للفاتح
        $this->get('/home/v2')->assertOk()
            ->assertSee('theme-dark', false)
            ->assertSee('name="mode" value="light"', false);

        $this->post('/user/theme-mode', ['mode' => 'purple'])->assertSessionHasErrors('mode');
    }

    public function test_cards_zone_stacks_extra_cards_in_the_last_column()
    {
        $util = app(HomeDashboardUtil::class);
        $available = array_fill_keys(['kpis', 'secondary', 'chart', 'top_products', 'forecast', 'attention', 'aging', 'payment_methods', 'heatmap', 'modules'], true);

        $default = $util->renderLayout($util->userLayout(new User()), $available);
        $this->assertSame(['chart', 'attention'], $default['main']);
        $this->assertSame([['top_products'], ['aging'], ['payment_methods', 'forecast']], $default['cards']);

        $layout = $util->userLayout(new User(['dashboard_layout' => json_encode([
            'order' => ['cards' => ['forecast', 'top_products']],
            'hidden' => ['aging'],
        ])]));
        $rendered = $util->renderLayout($layout, $available);
        $this->assertSame([['forecast'], ['top_products'], ['payment_methods']], $rendered['cards']);
    }

    private function addOpeningBalance($business, $type, $amount, $paid = 0, $location_id = null)
    {
        $contact = \App\Contact::create([
            'business_id' => $business->id,
            'type' => $type,
            'contact_type' => 'individual',
            'first_name' => 'Opening',
            'name' => 'Opening',
            'contact_id' => 'HOB-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ]);
        $transaction = \App\Transaction::create([
            'business_id' => $business->id,
            'location_id' => $location_id ?: $business->locations()->first()->id,
            'contact_id' => $contact->id,
            'type' => 'opening_balance',
            'status' => 'final',
            'payment_status' => 'due',
            'final_total' => $amount,
            'total_before_tax' => $amount,
            'transaction_date' => now(),
            'created_by' => $this->user->id,
        ]);
        if ($paid > 0) {
            \App\TransactionPayment::create([
                'transaction_id' => $transaction->id,
                'business_id' => $business->id,
                'amount' => $paid,
                'method' => 'cash',
                'paid_on' => now(),
                'created_by' => $this->user->id,
            ]);
        }

        return $contact;
    }

    public function test_outstanding_includes_what_is_left_of_the_opening_balances()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $util = app(HomeDashboardUtil::class);
        $before = $util->outstanding($business->id, null, 'all');

        $this->addOpeningBalance($business, 'customer', 1000, 300);
        $this->addOpeningBalance($business, 'both', 200);
        $this->addOpeningBalance($business, 'supplier', 500);
        $deleted = $this->addOpeningBalance($business, 'customer', 9000);
        $deleted->delete();

        $other = Business::where('id', '!=', $business->id)->firstOrFail();
        $this->addOpeningBalance($other, 'customer', 7000);

        $after = $util->outstanding($business->id, null, 'all');
        $this->assertEqualsWithDelta(900, $after['receivable'] - $before['receivable'], 0.001);
        $this->assertEqualsWithDelta(500, $after['payable'] - $before['payable'], 0.001);
        $this->assertEqualsWithDelta(900, $after['receivable_opening'] - $before['receivable_opening'], 0.001);
        $this->assertEqualsWithDelta(500, $after['payable_opening'] - $before['payable_opening'], 0.001);
    }

    public function test_outstanding_opening_balances_follow_the_location_limits()
    {
        $business = Business::has('locations', '>=', 2)->firstOrFail();
        $locations = $business->locations()->pluck('id');
        $util = app(HomeDashboardUtil::class);

        $inside = $util->outstanding($business->id, $locations[0], 'all')['receivable_opening'];
        $outside = $util->outstanding($business->id, $locations[1], 'all')['receivable_opening'];
        $permitted = $util->outstanding($business->id, null, [$locations[1]])['receivable_opening'];

        $this->addOpeningBalance($business, 'customer', 400, 0, $locations[0]);

        $this->assertEqualsWithDelta($inside + 400, $util->outstanding($business->id, $locations[0], 'all')['receivable_opening'], 0.001);
        $this->assertEqualsWithDelta($outside, $util->outstanding($business->id, $locations[1], 'all')['receivable_opening'], 0.001);
        $this->assertEqualsWithDelta($permitted, $util->outstanding($business->id, null, [$locations[1]])['receivable_opening'], 0.001);
    }

    public function test_home_shows_the_opening_part_under_the_total()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $this->addOpeningBalance($business, 'supplier', 500);

        $this->withSession(['user.business_id' => $business->id])->get('/home/v2')
            ->assertOk()
            ->assertSee(__('home_v2.stat_opening_part'));
    }

    private function addDocument($contact, $type, $amount, $paid = 0, $extra = [])
    {
        $transaction = \App\Transaction::create(array_merge([
            'business_id' => $contact->business_id,
            'location_id' => Business::find($contact->business_id)->locations()->first()->id,
            'contact_id' => $contact->id,
            'type' => $type,
            'status' => 'final',
            'payment_status' => 'due',
            'final_total' => $amount,
            'total_before_tax' => $amount,
            'transaction_date' => now(),
            'created_by' => $this->user->id,
        ], $extra));
        if ($paid > 0) {
            \App\TransactionPayment::create([
                'transaction_id' => $transaction->id,
                'business_id' => $contact->business_id,
                'amount' => $paid,
                'method' => 'cash',
                'paid_on' => now(),
                'created_by' => $this->user->id,
            ]);
        }

        return $transaction;
    }

    public function test_receivable_nets_returns_per_customer_and_keeps_credits_apart()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $util = app(HomeDashboardUtil::class);
        $before = $util->outstanding($business->id, null, 'all');

        $owes = $this->addOpeningBalance($business, 'customer', 0);
        $this->addDocument($owes, 'sell', 1000);
        $this->addDocument($owes, 'sell_return', 100);
        $this->addDocument($owes, 'sell', 500, 0, ['status' => 'draft']);
        $this->addDocument($owes, 'ledger_discount', 50, 0, ['sub_type' => 'sell_discount']);
        $overpaid = $this->addOpeningBalance($business, 'customer', 0);
        $this->addDocument($overpaid, 'sell', 100, 300);

        $after = $util->outstanding($business->id, null, 'all');
        $this->assertEqualsWithDelta(850, $after['receivable'] - $before['receivable'], 0.001);
        $this->assertEqualsWithDelta(200, $after['receivable_credit'] - $before['receivable_credit'], 0.001);
    }

    public function test_payable_nets_purchase_returns_per_supplier_and_keeps_credits_apart()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $util = app(HomeDashboardUtil::class);
        $before = $util->outstanding($business->id, null, 'all');

        $owed = $this->addOpeningBalance($business, 'supplier', 200);
        $this->addDocument($owed, 'purchase', 1000, 100);
        $this->addDocument($owed, 'purchase_return', 300, 50);
        $this->addDocument($owed, 'ledger_discount', 20, 0, ['sub_type' => 'purchase_discount']);
        $returned = $this->addOpeningBalance($business, 'supplier', 0);
        $this->addDocument($returned, 'purchase', 100);
        $this->addDocument($returned, 'purchase_return', 400);

        $after = $util->outstanding($business->id, null, 'all');
        $this->assertEqualsWithDelta(830, $after['payable'] - $before['payable'], 0.001);
        $this->assertEqualsWithDelta(300, $after['payable_credit'] - $before['payable_credit'], 0.001);
    }

    public function test_home_total_matches_the_contacts_list_kpi()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $session = ['user.business_id' => $business->id, 'business' => $business->toArray(), 'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.']];
        $outstanding = app(HomeDashboardUtil::class)->outstanding($business->id, null, 'all');

        foreach (['customer' => 'receivable', 'supplier' => 'payable'] as $type => $key) {
            $kpi = $this->withSession($session)->getJson('/contacts/list-summary?type='.$type)->assertOk()->json('due');
            $this->assertEqualsWithDelta($kpi, $outstanding[$key], 0.01, $type);
        }
    }

    public function test_home_shows_the_credit_balance_under_the_total()
    {
        $business = Business::whereHas('locations')->firstOrFail();
        $overpaid = $this->addOpeningBalance($business, 'customer', 0);
        $this->addDocument($overpaid, 'sell', 100, 300);

        $this->withSession(['user.business_id' => $business->id])->get('/home/v2')
            ->assertOk()
            ->assertSee(__('home_v2.stat_credit_receivable'));
    }
}
