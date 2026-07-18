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
        // Prefer the live public hostname over APP_URL (often left as localhost after migrate/seed).
        $requestHost = (string) request()->getHost();
        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            return strtolower($this->stripPort($requestHost));
        }

        $configured = (string) config('app.url');
        if ($configured !== '') {
            $host = parse_url($configured, PHP_URL_HOST);
            if (is_string($host) && $host !== '' && ! $this->isLoopbackHost($host)) {
                return strtolower($this->stripPort($host));
            }
            if (is_string($host) && $host !== '') {
                return strtolower($this->stripPort($host));
            }
        }

        if ($requestHost !== '') {
            return strtolower($this->stripPort($requestHost));
        }

        return 'localhost';
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($this->stripPort($host));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost');
    }

    private function stripPort(string $host): string
    {
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            return explode(':', $host)[0];
        }

        return $host;
    }
}
