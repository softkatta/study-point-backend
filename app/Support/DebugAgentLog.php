<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** Temporary debug-mode NDJSON helper (session 00f2f1). Never log secrets. */
final class DebugAgentLog
{
    private const SESSION = '00f2f1';

    private const CACHE_KEY = 'debug_agent_log_00f2f1';

    private const MAX_ENTRIES = 200;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(string $location, string $message, array $data = [], ?string $hypothesisId = null): void
    {
        try {
            $payload = [
                'sessionId' => self::SESSION,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'hypothesisId' => $hypothesisId,
                'timestamp' => (int) (microtime(true) * 1000),
                'source' => 'php',
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

            $workspaceLog = dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'debug-00f2f1.log';
            if (is_dir(dirname($workspaceLog)) && is_writable(dirname($workspaceLog))) {
                @file_put_contents($workspaceLog, json_encode($payload, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable) {
            // Never break licensing for debug logs.
        }
    }
}
