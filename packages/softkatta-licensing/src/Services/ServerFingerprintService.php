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

        // Prefer the live public request host when it differs from APP_URL.
        // Prevents Study Point APP_URL from poisoning a Kindergarten install (and vice versa).
        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            $request = strtolower($this->stripPort($requestHost));
            $configured = $appUrlHost !== null ? strtolower($this->stripPort($appUrlHost)) : null;

            if ($configured === null || $this->isLoopbackHost($configured) || $configured === $request) {
                return $request;
            }

            // Different public hosts: request wins for activate/verify against SoftKatta Admin domains.
            return $request;
        }

        if ($appUrlHost !== null && ! $this->isLoopbackHost($appUrlHost)) {
            return strtolower($this->stripPort($appUrlHost));
        }

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
