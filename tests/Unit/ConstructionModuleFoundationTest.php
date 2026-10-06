<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Modules\Construction\Http\Controllers\ContractController;
use ReflectionMethod;
use Tests\TestCase;

class ConstructionModuleFoundationTest extends TestCase
{
    public function test_module_configuration_and_routes_are_registered(): void
    {
        $this->assertSame('Construction', config('construction.name'));
        $this->assertSame('0.11.1', config('construction.module_version'));
        $this->assertTrue(Route::has('construction.dashboard'));
        $this->assertTrue(Route::has('construction.install.index'));
        $this->assertTrue(Route::has('construction.install.store'));
        $this->assertTrue(Route::has('construction.install.update'));
        $this->assertTrue(Route::has('construction.install.uninstall'));
        $this->assertTrue(Route::has('construction.projects.index'));
        $this->assertTrue(Route::has('construction.quotes.index'));
        $this->assertTrue(Route::has('construction.quotes.convert'));
        $this->assertTrue(Route::has('construction.quotes.pdf'));
        $this->assertTrue(Route::has('construction.projects.store'));
        $this->assertTrue(Route::has('construction.projects.show'));
        $this->assertTrue(Route::has('construction.projects.update'));
        $this->assertFalse(Route::has('construction.projects.structures.store'));
        $this->assertFalse(Route::has('construction.projects.structures.destroy'));
        $this->assertTrue(Route::has('construction.projects.contracts.create'));
        $this->assertTrue(Route::has('construction.projects.contracts.store'));
        $this->assertTrue(Route::has('construction.projects.contracts.show'));
        $this->assertTrue(Route::has('construction.projects.contracts.activate'));
        $this->assertTrue(Route::has('construction.projects.contracts.preview'));
        $this->assertTrue(Route::has('construction.projects.contracts.print'));
        $this->assertTrue(Route::has('construction.projects.contracts.pdf'));
        $this->assertTrue(Route::has('construction.projects.boq.index'));
        $this->assertTrue(Route::has('construction.projects.boq.items.store'));
        $this->assertTrue(Route::has('construction.projects.boq.approve'));
        $this->assertTrue(Route::has('construction.projects.boq.revise'));
        $this->assertTrue(Route::has('construction.cost-codes.index'));
        $this->assertTrue(Route::has('construction.labor.index'));
        $this->assertTrue(Route::has('construction.labor.show'));
        $this->assertTrue(Route::has('construction.labor.approve'));
        $this->assertTrue(Route::has('construction.projects.measurements.index'));
        $this->assertTrue(Route::has('construction.projects.measurements.approve'));
        $this->assertTrue(Route::has('construction.projects.certificates.index'));
        $this->assertTrue(Route::has('construction.projects.certificates.approve'));
        $this->assertTrue(Route::has('construction.boq.import-template'));
        $this->assertTrue(Route::has('construction.projects.boq.export'));
        $this->assertTrue(Route::has('construction.projects.boq.import.review'));
        $this->assertTrue(Route::has('construction.projects.boq.import.commit'));
    }

    public function test_module_translations_are_available(): void
    {
        app()->setLocale('ar');

        $this->assertSame('إدارة المقاولات', __('construction::lang.construction'));
        $this->assertSame(
            'صلاحية الدخول إلى إدارة المقاولات',
            __('construction::lang.access_module')
        );
    }

    public function test_contract_number_uses_project_code_and_two_digit_sequence(): void
    {
        $formatter = new ReflectionMethod(ContractController::class, 'formatContractNumber');

        $this->assertSame('PRJ-001-C01', $formatter->invoke(new ContractController(), 'PRJ-001', 1));
        $this->assertSame('PRJ-001-C12', $formatter->invoke(new ContractController(), 'PRJ-001', 12));
    }

    public function test_demo_seed_command_is_registered(): void
    {
        $this->assertTrue(array_key_exists('construction:seed-demo', \Artisan::all()));
    }

    public function test_modern_dashboard_shell_renders_with_all_section_tabs(): void
    {
        $source = file_get_contents(module_path('Construction', 'Resources/views/dashboard/index.blade.php'));
        $header = file_get_contents(module_path('Construction', 'Resources/views/partials/module_header.blade.php'));
        $sectionTabs = file_get_contents(module_path('Construction', 'Resources/views/partials/section_tabs.blade.php'));
        $layout = file_get_contents(module_path('Construction', 'Resources/views/layouts/module.blade.php'));

        $this->assertNotEmpty(\Illuminate\Support\Facades\Blade::compileString($source));
        $this->assertNotEmpty(\Illuminate\Support\Facades\Blade::compileString($header));
        $this->assertNotEmpty(\Illuminate\Support\Facades\Blade::compileString($sectionTabs));
        $this->assertStringContainsString('class="ct-dashboard', $layout);
        $this->assertStringNotContainsString('data-module-tab=', $header);
        $this->assertStringNotContainsString('ct-tabs ct-tabs--sticky', $header);
        $this->assertStringContainsString("route('construction.project-items.index'", $sectionTabs);
        $this->assertStringContainsString("route('construction.contracts.index'", $sectionTabs);
        $this->assertStringContainsString("route('construction.ipcs.index'", $sectionTabs);
        $this->assertStringNotContainsString("['materials', 'fa-cubes'", $sectionTabs);
        $this->assertStringContainsString("request()->routeIs('construction.overview.*')", $layout);

        foreach (['Resources/views/layouts/module.blade.php', 'Resources/views/partials/module_header.blade.php', 'Resources/views/partials/section_tabs.blade.php', 'Resources/views/projects/partials/form.blade.php', 'Resources/views/projects/partials/consultant_script.blade.php', 'Resources/views/projects/create.blade.php', 'Resources/views/projects/edit.blade.php', 'Resources/views/projects/show.blade.php', 'Resources/views/contracts/partials/form.blade.php', 'Resources/views/contracts/create.blade.php', 'Resources/views/contracts/edit.blade.php', 'Resources/views/contracts/show.blade.php', 'Resources/views/contracts/index.blade.php', 'Resources/views/contracts/print.blade.php', 'Resources/views/boq/index.blade.php', 'Resources/views/boq/workspace.blade.php', 'Resources/views/boq/show.blade.php', 'Resources/views/boq/import_review.blade.php', 'Resources/views/cost_codes/index.blade.php', 'Resources/views/measurements/index.blade.php', 'Resources/views/measurements/workspace.blade.php', 'Resources/views/measurements/show.blade.php', 'Resources/views/certificates/index.blade.php', 'Resources/views/certificates/workspace.blade.php', 'Resources/views/certificates/show.blade.php', 'Resources/views/dashboard/overview.blade.php', 'Resources/views/dashboard/costs.blade.php', 'Resources/views/dashboard/reports.blade.php'] as $view) {
            $this->assertNotEmpty(\Illuminate\Support\Facades\Blade::compileString(
                file_get_contents(module_path('Construction', $view))
            ));
        }

        $projectForm = file_get_contents(module_path('Construction', 'Resources/views/projects/partials/form.blade.php'));
        $measurementView = file_get_contents(module_path('Construction', 'Resources/views/measurements/show.blade.php'));
        $projectView = file_get_contents(module_path('Construction', 'Resources/views/projects/show.blade.php'));
        $this->assertStringNotContainsString('name="contract_number"', $projectForm);
        $this->assertStringContainsString('$suggestedCode', $projectForm);
        $this->assertStringContainsString('name="consultant_selection"', $projectForm);
        $this->assertStringContainsString('id="manual-consultant-wrap"', $projectForm);
        $consultantScript = file_get_contents(module_path('Construction', 'Resources/views/projects/partials/consultant_script.blade.php'));
        $this->assertStringContainsString("select2:select", $consultantScript);
        $this->assertStringContainsString('$manualWrap.toggle(isManual)', $consultantScript);
        $this->assertStringContainsString('name="status" value="draft"', $projectForm);
        $this->assertStringNotContainsString('structure_id', $measurementView);
        $this->assertStringNotContainsString('projects.structures.', $projectView);
    }
}
