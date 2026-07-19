<?php

namespace SoftKatta\Licensing\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;
use SoftKatta\Licensing\Support\LicenseErrorCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        try {
            $denied = $this->denyIfLicenseInvalid($request);
        } catch (DecryptException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Stored license tokens cannot be read. Enter your SoftKatta license key again on Restore access.',
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'data' => [
                    'redirect' => LicenseErrorCode::frontendPath(LicenseErrorCode::INVALID_INSTALL_TOKEN),
                ],
            ], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'License check failed. Try Restore access or contact SoftKatta support.',
                'error_code' => LicenseErrorCode::INVALID_LICENSE,
                'data' => [
                    'redirect' => LicenseErrorCode::frontendPath(LicenseErrorCode::INVALID_LICENSE),
                ],
            ], 403);
        }

        if ($denied !== null) {
            return $denied;
        }

        // Controller/app errors must stay real HTTP status codes — not remapped as license 403.
        return $next($request);
    }

    /**
     * @return Response|null Null when the request may proceed.
     */
    protected function denyIfLicenseInvalid(Request $request): ?Response
    {
        if (! $this->license->isInstalled()) {
            return null;
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
        // Always force SoftKatta when hard-blocked so Admin Activate restores on the next request.
        if ($this->license->isHardBlocked()) {
            $code = $this->license->state()->last_error_code ?: LicenseErrorCode::INVALID_LICENSE;

            $result = $this->license->verify(true);
            if ($result['ok'] ?? false) {
                return null;
            }
            $code = $result['error_code'] ?? $code;

            if ($code === LicenseErrorCode::INVALID_INSTALL_TOKEN) {
                $reactivated = $this->license->attemptAutoReactivate();
                if ($reactivated['ok'] ?? false) {
                    return null;
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

        // Public marketing GETs always re-check SoftKatta when interval is 0 (default)
        // so Admin Suspend stops the site on the next page load.
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
            || $request->is('api/v1/auth/*')
            // Public GETs always hit SoftKatta so Admin Suspend/Activate is immediate.
            || $isPublicGet;

        $needsCheck = $force
            || $request->isMethod('post')
            || $request->isMethod('put')
            || $request->isMethod('patch')
            || $request->isMethod('delete');

        if (! $needsCheck) {
            return null;
        }

        if (! $this->license->state()->install_token) {
            return response()->json([
                'success' => false,
                'message' => 'Product license is not activated.',
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'data' => ['redirect' => '/license/invalid-install-token'],
            ], 403);
        }

        // Auth + public marketing paths force SoftKatta (Admin status changes apply on next request).
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

        return null;
    }
}
