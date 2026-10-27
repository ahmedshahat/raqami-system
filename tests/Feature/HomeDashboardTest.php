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
}
