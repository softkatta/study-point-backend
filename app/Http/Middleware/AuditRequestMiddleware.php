<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestMiddleware
{
    public function __construct(private AuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $path = trim($request->path(), '/');
        if (str_contains($path, 'auth/login') || str_contains($path, 'auth/2fa')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $segments = explode('/', $path);
        $resource = $segments[2] ?? ($segments[1] ?? 'api');
        $resourceId = null;
        foreach ($segments as $segment) {
            if (ctype_digit($segment)) {
                $resourceId = (int) $segment;
                break;
            }
        }
        $action = strtolower($request->method()).'.'.$resource;

        $this->audit->log($action, $user, $resource, $resourceId, $request, [
            'path' => '/'.$path,
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
