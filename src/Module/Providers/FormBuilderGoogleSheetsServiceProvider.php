<?php

namespace RefinedDigital\GoogleSheets\Module\Providers;

use Illuminate\Support\ServiceProvider;
use RefinedDigital\CMS\Modules\Core\Aggregates\FormBuilderIntegrationAggregate;
use RefinedDigital\GoogleSheets\Commands\Install;
use RefinedDigital\GoogleSheets\Module\Classes\Process;

class FormBuilderGoogleSheetsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/google-sheets.php', 'google-sheets');
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../Config/google-sheets.php' => config_path('google-sheets.php'),
        ], 'google-sheets-config');

        // register the integration so it appears in each form's integrations panel
        app(FormBuilderIntegrationAggregate::class)->register('google-sheets', [
            'name'        => 'Google Sheets',
            'description' => 'Append each submission as a row to a Google Sheet',
            'processor'   => Process::class,
            // credentials live in .env; nothing to configure per-form
            'settings'    => [],
        ]);

        try {
            if ($this->app->runningInConsole()) {
                if (\DB::connection()->getDatabaseName() && !file_exists(config_path('google-sheets.php'))) {
                    $this->commands([
                        Install::class,
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }
}
