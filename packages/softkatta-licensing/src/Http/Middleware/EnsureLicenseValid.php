<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;
use SoftKatta\Licensing\Support\LicenseErrorCode;
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

        if (! $this->license->isCompanyApiConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'SoftKatta Product Integration is not configured. Create an integration in SoftKatta Admin and enter the API keys on this product.',
                'error_code' => LicenseErrorCode::COMPANY_API_NOT_CONFIGURED,
                'data' => [
                    'redirect' => LicenseErrorCode::frontendPath(LicenseErrorCode::COMPANY_API_NOT_CONFIGURED),
                ],
            ], 403);
        }

        // After SoftKatta suspend/revoke, block public marketing APIs too.
        // Always re-check SoftKatta — Admin Activate may revive sessions or clear suspend.
        if ($this->license->isHardBlocked()) {
            $code = $this->license->state()->last_error_code ?: LicenseErrorCode::INVALID_LICENSE;

            $result = $this->license->verify(true);
            if ($result['ok'] ?? false) {
                return $next($request);
            }
            $code = $result['error_code'] ?? $code;

            if ($code === LicenseErrorCode::INVALID_INSTALL_TOKEN) {
                $reactivated = $this->license->attemptAutoReactivate();
                if ($reactivated['ok'] ?? false) {
                    return $next($request);
                }
                $code = $reactivated['error_code'] ?? $code;
            }

            return response()->json([
                'success' => false,
                'message' => 'License access is blocked.',
                'error_code' => $code,
                'data' => [
                    'redirect' => LicenseErrorCode::frontendPath($code),
                ],
            ], 403);
        }

        // Public marketing GETs still require a live SoftKatta check (short cache) so Suspend stops the site.
        $isPublicGet = false;
        if ($request->isMethod('get')) {
            foreach ((array) config('softkatta.license_public_get_paths', []) as $pattern) {
                if ($request->is($pattern)) {
                    $isPublicGet = true;
                    break;
                }
            }
        }

        $force = $request->is('api/v1/auth/login')
            || $request->is('api/v1/license/verify')
            || (bool) $request->bearerToken()
            || $request->is('api/v1/auth/*');

        $needsCheck = $force
            || $isPublicGet
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
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'data' => ['redirect' => '/license/invalid-install-token'],
            ], 403);
        }

        // Authenticated / login always online; public uses short cache (verify_interval_minutes).
        $result = $this->license->verify($force);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'License verification failed.',
                'error_code' => $result['error_code'] ?? 'INVALID_LICENSE',
                'data' => [
                    'redirect' => LicenseErrorCode::frontendPath((string) ($result['error_code'] ?? LicenseErrorCode::INVALID_LICENSE)),
                ],
            ], 403);
        }

        return $next($request);
    }
}
