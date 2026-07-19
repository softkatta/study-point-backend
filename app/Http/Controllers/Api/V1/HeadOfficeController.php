<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeadOfficeAdminResource;
use App\Http\Resources\HeadOfficeResource;
use App\Services\HeadOfficeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeadOfficeController extends Controller
{
    public function __construct(private HeadOfficeService $headOffice) {}

    public function show(): JsonResponse
    {
        try {
            $office = $this->headOffice->find() ?? $this->headOffice->getOrCreate();

            return ApiResponse::success(new HeadOfficeResource($office));
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::success([
                'id' => null,
                'code' => 'HO',
                'name' => 'StudyPoint',
                'legal_name' => 'StudyPoint',
                'city' => '',
                'state' => '',
                'pincode' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'website' => '',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'currency_symbol' => '₹',
            ]);
        }
    }

    public function manageShow(): JsonResponse
    {
        return ApiResponse::success(new HeadOfficeAdminResource($this->headOffice->getOrCreate()));
    }

    public function update(Request $request): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate(HeadOfficeService::validationRules());
        $office = $this->headOffice->update($data);

        return ApiResponse::success(new HeadOfficeAdminResource($office), 'Head office updated');
    }

    private function requireSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden', 403);
        }

        return null;
    }
}
