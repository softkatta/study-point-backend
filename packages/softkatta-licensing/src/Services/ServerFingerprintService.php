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
        // Explicit override for SoftKatta Admin domain matching.
        $bound = $this->hostFromUrl((string) config('softkatta.bound_domain', ''));
        if ($bound !== null && ! $this->isLoopbackHost($bound)) {
            return strtolower($this->stripPort($bound));
        }

        // Prefer public frontend host (e.g. study-point.softkatta.in) over API host
        // (e.g. study-api.softkatta.in). SoftKatta Admin usually lists the SPA domain.
        $frontendHost = $this->hostFromUrl((string) (
            config('softkatta.frontend_url')
            ?: env('FRONTEND_URL', '')
            ?: config('app.frontend_url', '')
        ));
        if ($frontendHost !== null && ! $this->isLoopbackHost($frontendHost)) {
            return strtolower($this->stripPort($frontendHost));
        }

        $requestHost = (string) request()->getHost();
        $appUrlHost = $this->hostFromUrl((string) config('app.url'));

        // Prefer the live public request host when it differs from APP_URL.
        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            $request = strtolower($this->stripPort($requestHost));
            $configured = $appUrlHost !== null ? strtolower($this->stripPort($appUrlHost)) : null;

            if ($configured === null || $this->isLoopbackHost($configured) || $configured === $request) {
                return $request;
            }

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

    private function hostFromUrl(string $configured): ?string
    {
        $configured = trim($configured);
        if ($configured === '') {
            return null;
        }

        if (! str_contains($configured, '://')) {
            $configured = 'https://'.$configured;
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
