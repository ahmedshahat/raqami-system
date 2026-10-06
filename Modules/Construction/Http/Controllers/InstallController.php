<?php

namespace Modules\Construction\Http\Controllers;

use App\System;
use Composer\Semver\Comparator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class InstallController extends Controller
{
    private string $moduleName = 'construction';

    private string $moduleDisplayName = 'Construction';

    public function index()
    {
        $this->authorizeManageModules();
        abort_if(System::getProperty($this->moduleName.'_version'), 404);

        return view('construction::install.index', [
            'moduleVersion' => config('construction.module_version', '0.1.0'),
        ]);
    }

    public function install()
    {
        $this->authorizeManageModules();
        abort_if(System::getProperty($this->moduleName.'_version'), 404);

        return $this->runMigrations(__('construction::lang.module_installed_successfully'));
    }

    public function uninstall()
    {
        $this->authorizeManageModules();

        System::removeProperty($this->moduleName.'_version');

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.module_uninstalled_successfully'),
        ]);
    }

    public function update()
    {
        $this->authorizeManageModules();

        $installedVersion = System::getProperty($this->moduleName.'_version');
        $moduleVersion = config('construction.module_version', '0.1.0');

        abort_unless(
            $installedVersion && Comparator::greaterThan($moduleVersion, $installedVersion),
            404
        );

        return $this->runMigrations(__('construction::lang.module_updated_successfully'));
    }

    private function runMigrations(string $successMessage)
    {
        try {
            Artisan::call('module:migrate', [
                'module' => $this->moduleDisplayName,
                '--force' => true,
            ]);
            System::addProperty($this->moduleName.'_version', config('construction.module_version', '0.1.0'));

            $status = ['success' => 1, 'msg' => $successMessage];
        } catch (\Throwable $exception) {
            report($exception);
            $status = ['success' => 0, 'msg' => $exception->getMessage()];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $status);
    }

    private function authorizeManageModules(): void
    {
        abort_unless(auth()->user()?->can('manage_modules'), 403, 'Unauthorized action.');
    }
}
