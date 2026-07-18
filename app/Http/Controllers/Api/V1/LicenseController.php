<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;

class LicenseController extends Controller
{
    public function __construct(private LicenseService $license) {}

    public function entitlements(): JsonResponse
    {
        return ApiResponse::success($this->license->entitlements());
    }

    public function verify(Request $request): JsonResponse
    {
        $force = $request->boolean('force');
        $result = $this->license->verify($force);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'License verification failed.',
                'error_code' => $result['error_code'] ?? null,
                'data' => null,
            ], 403);
        }

        return ApiResponse::success([
            'from_cache' => $result['from_cache'] ?? false,
            'grace' => $result['grace'] ?? false,
            'entitlements' => $this->license->entitlements(),
        ], 'License valid.');
    }
}
