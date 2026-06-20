<?php

namespace App\Http\Middleware;

use App\Services\SecurityPolicyService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceIpWhitelist
{
    public function __construct(private SecurityPolicyService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->security->shouldEnforceIpWhitelist($user)) {
            return $next($request);
        }

        if ($this->security->isIpAllowed($request->ip() ?? '')) {
            return $next($request);
        }

        return ApiResponse::error('Access denied. Your IP is not whitelisted for admin access.', 403);
    }
}
