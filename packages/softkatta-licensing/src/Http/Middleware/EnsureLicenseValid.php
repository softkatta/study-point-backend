<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseValid
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('softkatta.enabled', true)) {
            return $next($request);
        }

        foreach ((array) config('softkatta.license_exempt_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        if (! $this->license->isInstalled()) {
            return $next($request);
        }

        if ($request->isMethod('get')) {
            foreach ((array) config('softkatta.license_public_get_paths', []) as $pattern) {
                if ($request->is($pattern)) {
                    return $next($request);
                }
            }
        }

        $force = $request->is('api/v1/auth/login') || $request->is('api/v1/license/verify');
        $needsCheck = $force
            || $request->bearerToken()
            || $request->is('api/v1/auth/*')
            || $request->isMethod('post')
            || $request->isMethod('put')
            || $request->isMethod('patch')
            || $request->isMethod('delete');

        if (! $needsCheck) {
            return $next($request);
        }

        if (! $this->license->state()->install_token) {
            return response()->json([
                'success' => false,
                'message' => 'Product license is not activated.',
                'error_code' => 'INVALID_LICENSE',
                'data' => ['redirect' => '/install'],
            ], 403);
        }

        $result = $this->license->verify($force);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'License verification failed.',
                'error_code' => $result['error_code'] ?? 'INVALID_LICENSE',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
