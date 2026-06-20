<?php

namespace App\Http\Middleware;

use App\Services\SecurityPolicyService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function __construct(private SecurityPolicyService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->security->shouldForceHttps() || $request->secure()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error('HTTPS is required.', 426);
        }

        return redirect()->secure($request->getRequestUri());
    }
}
