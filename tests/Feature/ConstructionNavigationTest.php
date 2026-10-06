<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionNavigationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_primary_navigation_is_not_repeated_inside_module_pages(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id,
            'code' => 'TEST-NAV-'.uniqid(),
            'name' => 'Navigation project',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id,
            'version_number' => 1,
            'name' => 'Navigation items',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->get(route('construction.dashboard'))->assertRedirect(route('construction.overview.index'));

        $pages = [
            route('construction.quotes.index'),
            route('construction.projects.index'),
            route('construction.project-items.index'),
            route('construction.contracts.index'),
            route('construction.measurements.index'),
            route('construction.ipcs.index'),
            route('construction.costs.index'),
            route('construction.subcontractors.index'),
            route('construction.reports.index'),
            route('construction.overview.index'),
            route('construction.projects.show', $project->id),
            route('construction.projects.boq.show', [$project->id, $boq->id]),
            route('construction.projects.contracts.create', $project->id),
            route('construction.projects.measurements.index', $project->id),
            route('construction.projects.certificates.index', $project->id),
            route('construction.cost-codes.index'),
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url);
            $this->assertStringNotContainsString('data-module-tab=', $response->getContent(), $url);
            $this->assertStringNotContainsString('ct-tabs ct-tabs--sticky', $response->getContent(), $url);
        }
    }

    public function test_standalone_sections_do_not_render_unrelated_internal_tabs(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $pages = [
            route('construction.overview.index'),
            route('construction.quotes.index'),
            route('construction.costs.index'),
            route('construction.subcontractors.index'),
            route('construction.reports.index'),
        ];

        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('ct-section-tabs', $html, $url);
            $this->assertStringNotContainsString('data-section-tab=', $html, $url);
        }
    }

    public function test_grouped_sections_render_the_expected_internal_tabs(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $projectPages = [
            [route('construction.projects.index'), 'projects'],
            [route('construction.project-items.index'), 'project-items'],
            [route('construction.contracts.index'), 'contracts'],
        ];
        foreach ($projectPages as [$url, $active]) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSectionTabs($html, ['projects', 'project-items', 'contracts'], $active, $url);
        }

        $progressPages = [
            [route('construction.measurements.index'), 'measurements'],
            [route('construction.ipcs.index'), 'certificates'],
        ];
        foreach ($progressPages as [$url, $active]) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSectionTabs($html, ['measurements', 'certificates'], $active, $url);
        }

    }

    public function test_large_dashboard_hero_only_appears_on_overview(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->get(route('construction.overview.index'))
            ->assertOk()
            ->assertSee('ct-hero ct-hero--compact', false);

        foreach ([
            route('construction.quotes.index'),
            route('construction.projects.index'),
            route('construction.measurements.index'),
            route('construction.costs.index'),
            route('construction.subcontractors.index'),
            route('construction.reports.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertDontSee('ct-hero ct-hero--compact', false);
        }
    }

    public function test_sidebar_contains_only_the_seven_requested_first_level_items(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $html = $this->get(route('construction.overview.index'))->assertOk()->getContent();
        preg_match('/<aside class="side-bar.*?<\/aside>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'The admin sidebar could not be located.');
        $sidebar = $matches[0];

        $expectedRoutes = [
            route('construction.overview.index'),
            route('construction.quotes.index'),
            route('construction.projects.index'),
            route('construction.measurements.index'),
            route('construction.costs.index'),
            route('construction.subcontractors.index'),
            route('construction.reports.index'),
        ];
        foreach ($expectedRoutes as $route) {
            $this->assertSame(1, substr_count($sidebar, 'href="'.$route.'"'), $route);
        }

        $this->assertStringNotContainsString(route('construction.project-items.index'), $sidebar);
        $this->assertStringNotContainsString(route('construction.contracts.index'), $sidebar);
        $this->assertStringNotContainsString(route('construction.ipcs.index'), $sidebar);
        $this->assertStringContainsString('مقاولو الباطن', $sidebar);
        $this->assertStringNotContainsString('مقاولين الباطن', $sidebar);
    }

    public function test_labor_draft_filter_does_not_activate_sales_draft_menu(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $html = $this->get(route('construction.labor.index', ['status' => 'draft']))->assertOk()->getContent();
        preg_match('/<a href="[^"]*\/sells\/create\?status=draft"[^>]*class="([^"]*)"/i', $html, $matches);

        $this->assertNotEmpty($matches, 'The sales draft sidebar link could not be located.');
        $this->assertStringNotContainsString('tw-text-primary-700', $matches[1]);
    }

    public function test_contracts_page_contains_actions_dropdown(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id,
            'code' => 'TEST-CTR-'.uniqid(),
            'name' => 'Contracts Page Project',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $contract = $project->contracts()->create([
            'business_id' => $business->id,
            'sequence_number' => 1,
            'contract_number' => 'CTR-TEST-01',
            'contract_type' => 'fixed_price',
            'original_value' => 150000,
            'retention_percent' => 5,
            'is_primary' => true,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('construction.contracts.index'))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('ct-contract-menu-toggle', $html);
        $this->assertStringContainsString('ct-contract-menu', $html);
        $this->assertStringContainsString(__('construction::lang.view_contract'), $html);
        $this->assertStringContainsString(__('construction::lang.print_preview'), $html);
        $this->assertStringContainsString(route('construction.projects.contracts.show', [$project->id, $contract->id]), $html);
        $this->assertStringContainsString(route('construction.projects.contracts.preview', [$project->id, $contract->id]), $html);
    }

    public function test_module_root_redirects_to_the_independent_overview_page(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->get(route('construction.dashboard'))
            ->assertRedirect(route('construction.overview.index'));
    }

    private function assertSectionTabs(string $content, array $tabs, string $activeTab, string $page): void
    {
        $this->assertStringContainsString('ct-section-tabs', $content, $page);
        $lastPosition = -1;
        foreach ($tabs as $tab) {
            $needle = 'data-section-tab="'.$tab.'"';
            $this->assertSame(1, substr_count($content, $needle), $page.' / '.$tab);
            $position = strpos($content, $needle);
            $this->assertNotFalse($position, $page.' / '.$tab);
            $this->assertGreaterThan($lastPosition, $position, $page.' / '.$tab);
            $lastPosition = $position;
        }

        preg_match('/class="([^"]*is-active[^"]*)"[^>]*data-section-tab="'.preg_quote($activeTab, '/').'"/', $content, $matches);
        $this->assertNotEmpty($matches, 'Missing active internal tab '.$activeTab.' on '.$page);
    }
}
