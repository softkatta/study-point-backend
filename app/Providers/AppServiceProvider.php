<?php

namespace App\Providers;

use App\Services\HeadOfficeService;
use App\Services\StudentAccountService;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StudentAccountService::class);
    }

    public function boot(): void
    {
        $this->configureOutboundHttp();
        $this->configureSoftKattaSeatUsage();

        Gate::before(function ($user, $ability) {
            return $user?->hasRole(Roles::SUPER_ADMIN) ? true : null;
        });

        try {
            $headOffice = app(HeadOfficeService::class)->find();
            $timezone = $headOffice?->timezone;
            if (! empty($timezone)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        } catch (\Throwable) {
            // DB may be unavailable during install/migrate
        }
    }

    private function configureSoftKattaSeatUsage(): void
    {
        config([
            'softkatta.seat_usage_resolver' => static function (): array {
                return [
                    'users' => \App\Models\User::query()->count(),
                    'students' => \App\Models\Student::query()->count(),
                ];
            },
        ]);
    }

    private function configureOutboundHttp(): void
    {
        $bundle = config('http.ca_bundle');
        if (! $bundle) {
            $default = base_path('storage/certs/cacert.pem');
            if (is_file($default)) {
                $bundle = $default;
            }
        }

        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            Http::globalOptions(['verify' => $bundle]);

            return;
        }

        if (! config('http.verify_ssl') && $this->app->environment('local')) {
            Http::globalOptions(['verify' => false]);
        }
    }
}
