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

    public function admin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $this->install->createAdmin($data);

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 'Administrator account created.');
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->license->activate($data['license_key']);

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
