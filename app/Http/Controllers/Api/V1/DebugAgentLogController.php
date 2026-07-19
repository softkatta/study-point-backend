<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Temporary debug-mode log relay (session 00f2f1). HTTPS pages cannot POST to http://127.0.0.1.
 */
class DebugAgentLogController extends Controller
{
    private const SESSION = '00f2f1';

    private const CACHE_KEY = 'debug_agent_log_00f2f1';

    private const MAX_ENTRIES = 200;

    public function store(Request $request): JsonResponse
    {
        if ($request->header('X-Debug-Session-Id') !== self::SESSION) {
            return ApiResponse::error('Forbidden', 403);
        }

        $payload = [
            'sessionId' => self::SESSION,
            'location' => (string) $request->input('location', ''),
            'message' => (string) $request->input('message', ''),
            'data' => $request->input('data', []),
            'hypothesisId' => $request->input('hypothesisId'),
            'timestamp' => (int) ($request->input('timestamp') ?: (int) (microtime(true) * 1000)),
            'runId' => $request->input('runId'),
            'source' => 'http-relay',
        ];

        $entries = Cache::get(self::CACHE_KEY, []);
        if (! is_array($entries)) {
            $entries = [];
        }
        $entries[] = $payload;
        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
        }
        Cache::put(self::CACHE_KEY, $entries, now()->addHours(6));

        return ApiResponse::success(['stored' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->header('X-Debug-Session-Id') !== self::SESSION
            && $request->query('session') !== self::SESSION) {
            return ApiResponse::error('Forbidden', 403);
        }

        $entries = Cache::get(self::CACHE_KEY, []);

        return ApiResponse::success([
            'sessionId' => self::SESSION,
            'count' => is_array($entries) ? count($entries) : 0,
            'entries' => is_array($entries) ? $entries : [],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($request->header('X-Debug-Session-Id') !== self::SESSION) {
            return ApiResponse::error('Forbidden', 403);
        }

        Cache::forget(self::CACHE_KEY);

        return ApiResponse::success(['cleared' => true]);
    }
}
