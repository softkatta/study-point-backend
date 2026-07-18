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
        $requestHost = (string) request()->getHost();
        $appUrlHost = $this->hostFromAppUrl();

        // 1) Non-loopback APP_URL wins — wizard writes the public site URL (not the API host).
        if ($appUrlHost !== null && ! $this->isLoopbackHost($appUrlHost)) {
            return strtolower($this->stripPort($appUrlHost));
        }

        // 2) Before APP_URL is fixed, use the live request host when it is public.
        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            return strtolower($this->stripPort($requestHost));
        }

        // 3) Fall back to whatever we have (often localhost right after migrate/seed).
        if ($appUrlHost !== null && $appUrlHost !== '') {
            return strtolower($this->stripPort($appUrlHost));
        }

        if ($requestHost !== '') {
            return strtolower($this->stripPort($requestHost));
        }

        return 'localhost';
    }

    private function hostFromAppUrl(): ?string
    {
        $configured = (string) config('app.url');
        if ($configured === '') {
            return null;
        }

        $host = parse_url($configured, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
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
