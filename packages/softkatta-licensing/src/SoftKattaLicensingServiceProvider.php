<?php

namespace SoftKatta\Licensing;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use SoftKatta\Licensing\Console\HeartbeatLicenseCommand;
use SoftKatta\Licensing\Console\RefreshLicenseTokenCommand;
use SoftKatta\Licensing\Console\VerifyLicenseCommand;
use SoftKatta\Licensing\Http\Middleware\EnsureInstalled;
use SoftKatta\Licensing\Http\Middleware\EnsureLicenseValid;
use SoftKatta\Licensing\Http\Middleware\EnsureNotInstalled;
use SoftKatta\Licensing\Services\CompanyApiClient;
use SoftKatta\Licensing\Services\InstallOrchestrator;
use SoftKatta\Licensing\Services\LicenseService;
use SoftKatta\Licensing\Services\ServerFingerprintService;

class SoftKattaLicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/softkatta.php', 'softkatta');

        $this->app->singleton(ServerFingerprintService::class);
        $this->app->singleton(CompanyApiClient::class);
        $this->app->singleton(LicenseService::class);
        $this->app->singleton(InstallOrchestrator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/softkatta.php' => config_path('softkatta.php'),
        ], 'softkatta-licensing-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyLicenseCommand::class,
                RefreshLicenseTokenCommand::class,
                HeartbeatLicenseCommand::class,
            ]);
        }

        $router = $this->app['router'];
        $router->aliasMiddleware('softkatta.installed', EnsureInstalled::class);
        $router->aliasMiddleware('softkatta.license', EnsureLicenseValid::class);
        $router->aliasMiddleware('softkatta.not_installed', EnsureNotInstalled::class);
        $router->aliasMiddleware('install.not_completed', EnsureNotInstalled::class);
    }

    public static function schedule(Schedule $schedule): void
    {
        $schedule->command('license:verify')->daily();
        $schedule->command('license:heartbeat')->hourly();
        $schedule->command('license:refresh-token')->weekly();
    }
}
