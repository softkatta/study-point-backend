<?php

namespace App\Providers;

use App\Services\HeadOfficeService;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
}
