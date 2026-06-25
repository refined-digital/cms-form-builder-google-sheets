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
            'icon'        => $this->icon(),
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

    /**
     * Google Sheets glyph (inline SVG, currentColor) shown in the integrations panel.
     */
    protected function icon(): string
    {
        return '<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">'
            .'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 2 4 4h-4V4ZM8 13h3v2H8v-2Zm0 3h3v2H8v-2Zm5 0h3v2h-3v-2Zm0-3h3v2h-3v-2Z"/>'
            .'</svg>';
    }
}
