<?php

namespace SoftKatta\Licensing\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ServerFingerprintService
{
    public function generate(): string
    {
        $machineIdPath = storage_path('app/server-machine-id');
        if (! File::exists($machineIdPath)) {
            File::ensureDirectoryExists(dirname($machineIdPath));
            File::put($machineIdPath, (string) Str::uuid());
        }

        $parts = [
            gethostname() ?: 'unknown-host',
            PHP_OS_FAMILY,
            php_uname('m'),
            substr((string) config('app.key'), 0, 16),
            trim(File::get($machineIdPath)),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public function currentDomain(): string
    {
        $configured = config('app.url');
        if ($configured) {
            $host = parse_url($configured, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return strtolower($this->stripPort($host));
            }
        }

        return strtolower($this->stripPort((string) request()->getHost()));
    }

    private function stripPort(string $host): string
    {
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            return explode(':', $host)[0];
        }

        return $host;
    }
}
