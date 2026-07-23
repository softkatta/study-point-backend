<?php

namespace App\Http\Middleware;

use App\Services\SecurityPolicyService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function __construct(private SecurityPolicyService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return $next($request);
        }

        $minutes = (int) ($this->security->config()['session_timeout_minutes'] ?? 0);
        if ($minutes <= 0) {
            return $next($request);
        }

        $cacheKey = "token_activity:{$token->id}";
        $seedTimestamp = $this->extractTimestamp($token->last_used_at)
            ?? $this->extractTimestamp($token->created_at);
        $cachedTimestamp = Cache::get($cacheKey);
        $lastActivityTs = is_numeric($cachedTimestamp)
            ? (int) $cachedTimestamp
            : ($seedTimestamp ?? now()->timestamp);

        if (now()->timestamp - $lastActivityTs > ($minutes * 60)) {
            $token->delete();
            Cache::forget($cacheKey);

            return ApiResponse::error('Session expired due to inactivity. Please login again.', 401, [
                'reason' => 'timeout',
            ]);
        }

        Cache::put($cacheKey, now()->timestamp, now()->addDays(30));

        return $next($request);
    }

    private function extractTimestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $parsed = strtotime($value);

            return $parsed === false ? null : $parsed;
        }

        return null;
    }
}
