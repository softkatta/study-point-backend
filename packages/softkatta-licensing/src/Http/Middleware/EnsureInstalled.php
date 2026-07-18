<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use SoftKatta\Licensing\Services\LicenseService;
use SoftKatta\Licensing\Support\LicenseErrorCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        } catch (Throwable $e) {
            // Already installed but DB credentials broken — never send users back to /install.
            if (File::exists(storage_path('app/installed'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection failed. Fix DB_* credentials in .env. Do not re-run the install wizard.',
                    'error_code' => 'DATABASE_UNAVAILABLE',
                    'data' => ['redirect' => '/license/database-unavailable'],
                ], 503);
            }

            $installed = false;
        }

        if (! $installed) {
            // Lock file means install completed even if license_states is temporarily unreadable.
            if (File::exists(storage_path('app/installed'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection failed. Fix DB_* credentials in .env. Do not re-run the install wizard.',
                    'error_code' => 'DATABASE_UNAVAILABLE',
                    'data' => ['redirect' => '/license/database-unavailable'],
                ], 503);
            }

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
