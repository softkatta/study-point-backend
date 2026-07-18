<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->license->isInstalled() && ! $request->is('api/v1/install/status')) {
            return response()->json([
                'success' => false,
                'message' => 'Application is already installed. Installer is locked.',
                'error_code' => 'ALREADY_INSTALLED',
            ], 410);
        }

        return $next($request);
    }
}
