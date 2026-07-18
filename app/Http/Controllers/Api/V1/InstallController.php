<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SoftKatta\InstallService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;

class InstallController extends Controller
{
    public function __construct(
        private InstallService $install,
        private LicenseService $license,
    ) {}

    public function status(): JsonResponse
    {
        return ApiResponse::success($this->install->status());
    }

    public function requirements(): JsonResponse
    {
        return ApiResponse::success($this->install->requirements());
    }

    public function database(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string'],
            'port' => ['nullable', 'string'],
            'database' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->install->configureDatabase($data);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'Database configured.');
    }

    public function companyApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_api_url' => ['required', 'url', 'max:500'],
            'public_api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'product_slug' => ['required', 'string', 'max:100'],
            'product_version' => ['nullable', 'string', 'max:50'],
            'app_url' => ['required', 'url', 'max:500'],
            'require_https' => ['nullable', 'boolean'],
            'offline_grace_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'verify_interval_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        try {
            $result = $this->install->configureCompanyApi($data);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'SoftKatta Company API configured.');
    }

    public function admin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $user = $this->install->createAdmin($data);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 'Administrator account created.');
    }

    public function activate(Request $request): JsonResponse
    {
        if (! $this->install->isCompanyApiConfigured()) {
            return ApiResponse::error(
                'Configure SoftKatta Company API in the install wizard before activating a license.',
                422,
                null,
                'COMPANY_API_NOT_CONFIGURED',
            );
        }

        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->license->activate($data['license_key']);
        } catch (\Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() ?: 'License activation failed.',
                422,
                null,
                'ACTIVATION_FAILED',
            );
        }

        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error(
                $result['message'] ?? 'Activation failed.',
                422,
                null,
                $result['error_code'] ?? 'INVALID_LICENSE',
            );
        }

        return ApiResponse::success([
            'installation_id' => $result['data']['installation_id'] ?? null,
            'bound_domain' => $result['data']['bound_domain'] ?? null,
            'configuration_profile' => $result['data']['configuration_profile'] ?? null,
        ], 'License activated.');
    }

    public function downloadConfiguration(): JsonResponse
    {
        try {
            $result = $this->install->downloadConfiguration();
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'Configuration ready.');
    }

    public function migrate(): JsonResponse
    {
        try {
            $result = $this->install->migrate();
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }

        return ApiResponse::success($result, 'Migrations completed.');
    }

    public function complete(): JsonResponse
    {
        try {
            $result = $this->install->complete();
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'Installation complete.');
    }
}
