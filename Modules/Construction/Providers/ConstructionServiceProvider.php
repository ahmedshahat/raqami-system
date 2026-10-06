<?php

namespace Modules\Construction\Providers;

use App\Transaction;
use Illuminate\Support\ServiceProvider;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionMaterialDocument;

class ConstructionServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Construction';

    protected string $moduleNameLower = 'construction';

    public function boot(): void
    {
        Transaction::resolveRelationUsing('constructionProject', fn (Transaction $transaction) =>
            $transaction->belongsTo(ConstructionProject::class, 'construction_project_id'));
        Transaction::resolveRelationUsing('constructionProjectItem', fn (Transaction $transaction) =>
            $transaction->belongsTo(ConstructionBoqItem::class, 'construction_boq_item_id'));
        Transaction::resolveRelationUsing('constructionMaterialDocument', fn (Transaction $transaction) =>
            $transaction->belongsTo(ConstructionMaterialDocument::class, 'construction_material_document_id'));

        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Construction\Console\SeedConstructionDemoCommand::class,
            ]);
        }
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom([$sourcePath], $this->moduleNameLower);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);
        $sourcePath = is_dir($langPath)
            ? $langPath
            : module_path($this->moduleName, 'Resources/lang');

        $this->loadTranslationsFrom($sourcePath, $this->moduleNameLower);
    }
}
