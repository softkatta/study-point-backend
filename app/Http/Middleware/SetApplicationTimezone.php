<?php

namespace App\Http\Middleware;

use App\Services\HeadOfficeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationTimezone
{
    public function __construct(private HeadOfficeService $headOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $timezone = $this->headOffice->find()?->timezone;
            if (! empty($timezone)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        } catch (\Throwable) {
            // DB may be unavailable during install/migrate
        }

        return $next($request);
    }
}
