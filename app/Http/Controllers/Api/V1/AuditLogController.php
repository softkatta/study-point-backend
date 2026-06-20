<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only super admin can view audit logs.', 403);
        }

        return ApiResponse::success($this->audit->recent(
            $request->integer('limit', 100),
            $request->integer('user_id') ?: null,
        ));
    }

    public function purge(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only super admin can purge audit logs.', 403);
        }

        $deleted = $this->audit->purgeExpired();

        return ApiResponse::success(['deleted' => $deleted], "Purged {$deleted} expired audit log(s)");
    }
}
