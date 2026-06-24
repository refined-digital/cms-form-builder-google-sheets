<?php

namespace RefinedDigital\GoogleSheets\Commands;

use Illuminate\Console\Command;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refinedCMS:install-form-builder-google-sheets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs the form builder google sheets module';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->updateEnvFile();
        $this->publishConfig();
        $this->info('Google Sheets Form Builder has been successfully installed');
    }

    protected function updateEnvFile()
    {
        $env = app()->environmentFilePath();
        $file = file_get_contents($env);

        // only add the keys that aren't already present
        $vars = ['GOOGLE_SHEETS_ID', 'GOOGLE_API_KEY', 'GOOGLE_AUTH_CONFIG'];
        $missing = array_filter($vars, fn ($var) => ! preg_match('/^'.$var.'=/m', $file));

        if ($missing) {
            $file .= "\n\n".implode("\n", array_map(fn ($var) => $var.'=', $missing));
            file_put_contents($env, $file);
        }
    }

    protected function publishConfig()
    {
        \Artisan::call('vendor:publish', [
            '--tag' => 'google-sheets-config',
        ]);
    }
}
