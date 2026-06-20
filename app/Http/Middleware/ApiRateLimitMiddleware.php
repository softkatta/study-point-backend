<?php

namespace App\Http\Middleware;

use App\Services\SecurityPolicyService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    public function __construct(private SecurityPolicyService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limit = max(30, $this->security->apiRateLimit());
        $key = 'api-rate:'.($request->user()?->id ?: $request->ip());

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error("Too many requests. Try again in {$seconds} seconds.", 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
