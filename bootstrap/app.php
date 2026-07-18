<?php

use SoftKatta\Licensing\Http\Middleware\EnsureInstalled;
use SoftKatta\Licensing\Http\Middleware\EnsureLicenseValid;
use SoftKatta\Licensing\Http\Middleware\EnsureNotInstalled;
use SoftKatta\Licensing\SoftKattaLicensingServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\ApiRateLimitMiddleware::class,
            \App\Http\Middleware\SetApplicationTimezone::class,
            EnsureInstalled::class,
            EnsureLicenseValid::class,
        ]);
        $middleware->alias([
            'ip.whitelist' => \App\Http\Middleware\EnforceIpWhitelist::class,
            'session.timeout' => \App\Http\Middleware\EnforceSessionTimeout::class,
            'audit.request' => \App\Http\Middleware\AuditRequestMiddleware::class,
            'install.not_completed' => EnsureNotInstalled::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('audit:purge')->daily();
        $schedule->command('subscriptions:sync-status')->daily();
        $schedule->command('whatsapp:send-reminders')->daily();
        $schedule->command('biometric:sync-logs')->everyFiveMinutes();
        SoftKattaLicensingServiceProvider::schedule($schedule);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
