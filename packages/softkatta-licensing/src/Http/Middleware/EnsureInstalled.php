<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;
use SoftKatta\Licensing\Support\LicenseErrorCode;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('softkatta.enabled', true)) {
            return $next($request);
        }

        if ($request->is('api/v1/install/*') || $request->is('up')) {
            return $next($request);
        }

        try {
            $installed = $this->license->isInstalled();
        } catch (\Throwable) {
            // Missing tables / DB not ready — treat as not installed (fail closed).
            $installed = false;
        }

        if (! $installed) {
            return response()->json([
                'success' => false,
                'message' => 'Application is not installed.',
                'error_code' => LicenseErrorCode::NOT_INSTALLED,
                'data' => ['redirect' => '/install'],
            ], 503);
        }

        return $next($request);
    }
}
