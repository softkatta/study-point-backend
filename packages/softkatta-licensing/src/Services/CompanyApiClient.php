<?php

namespace SoftKatta\Licensing\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SoftKatta\Licensing\Support\HmacSigner;
use SoftKatta\Licensing\Support\LicenseErrorCode;

class CompanyApiClient
{
    public function __construct(private ServerFingerprintService $fingerprint) {}

    public function activate(string $licenseKey, ?string $installationId = null): array
    {
        $body = ['license_key' => $licenseKey];
        if (filled($installationId)) {
            $body['installation_id'] = $installationId;
        }

        return $this->request('POST', '/activate', $body, includeInstallToken: false, installationId: $installationId);
    }

    public function verify(string $installToken, string $installationId, array $usage = []): array
    {
        $body = $usage === [] ? [] : ['usage' => $usage];

        return $this->request('POST', '/verify', $body, $installToken, $installationId);
    }

    public function refreshToken(string $refreshToken, string $installationId): array
    {
        return $this->request('POST', '/refresh-token', [
            'refresh_token' => $refreshToken,
        ], includeInstallToken: false, installationId: $installationId);
    }

    public function modules(string $installToken, string $installationId): array
    {
        return $this->request('GET', '/modules', [], $installToken, $installationId);
    }

    public function limits(string $installToken, string $installationId): array
    {
        return $this->request('GET', '/limits', [], $installToken, $installationId);
    }

    public function addons(string $installToken, string $installationId): array
    {
        return $this->request('GET', '/addons', [], $installToken, $installationId);
    }

    public function heartbeat(string $installToken, string $installationId, array $usage = []): array
    {
        $body = $usage === [] ? [] : ['usage' => $usage];

        return $this->request('POST', '/heartbeat', $body, $installToken, $installationId);
    }

    private function request(
        string $method,
        string $path,
        array $body = [],
        ?string $installToken = null,
        ?string $installationId = null,
        bool $includeInstallToken = true,
    ): array {
        $base = rtrim((string) config('softkatta.company_api_url'), '/');
        $publicKey = trim((string) config('softkatta.public_api_key'));
        $secret = $this->normalizeSecret((string) config('softkatta.api_secret'));

        if ($base === '') {
            return $this->configError('SoftKatta Company API URL is not configured.');
        }

        if ($publicKey === '' || $secret === '') {
            return $this->configError('SoftKatta API credentials are not configured.');
        }

        if (
            config('softkatta.require_https')
            && ! app()->environment(['local', 'testing'])
            && ! str_starts_with($base, 'https://')
        ) {
            return $this->configError('Company API URL must use HTTPS in production.');
        }

        $endpoint = '/'.ltrim($path, '/');
        $url = $base.$endpoint;
        $parsedPath = $this->signingPath($base, $endpoint);

        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? ''
            : (string) json_encode($body, JSON_UNESCAPED_SLASHES);

        $domain = $this->resolveRequestDomain($endpoint);
        $fp = $this->fingerprint->generate();
        $productSlug = trim((string) config('softkatta.product_slug'));
        $productVersion = trim((string) config('softkatta.product_version'));
        $installationId = (string) ($installationId ?? '');
        $timestamp = (string) time();
        $nonce = Str::random(32);

        $canonical = HmacSigner::canonicalString(
            $method,
            $parsedPath,
            $timestamp,
            $nonce,
            $productSlug,
            $domain,
            $productVersion,
            $installationId,
            $fp,
            $rawBody,
        );

        $signature = HmacSigner::sign($canonical, $secret);

        $headers = [
            'Authorization' => 'Bearer '.$publicKey,
            'X-Product-Slug' => $productSlug,
            'X-Domain' => $domain,
            'X-Product-Version' => $productVersion,
            'X-Installation-Id' => $installationId,
            'X-Server-Fingerprint' => $fp,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($includeInstallToken && $installToken) {
            $headers['X-Install-Token'] = $installToken;
        }

        try {
            $pending = Http::withHeaders($headers)->timeout(8)->connectTimeout(5)->withBody($rawBody !== '' ? $rawBody : '{}', 'application/json');
            $response = match (strtoupper($method)) {
                'GET' => Http::withHeaders($headers)->timeout(8)->connectTimeout(5)->get($url),
                'POST' => $pending->send('POST', $url),
                default => throw new RuntimeException('Unsupported method'),
            };
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'unavailable' => true,
                'error_code' => 'COMPANY_API_UNAVAILABLE',
                'message' => 'Company API unavailable: '.$e->getMessage(),
                'data' => null,
                'status' => 0,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'unavailable' => true,
                'error_code' => 'COMPANY_API_UNAVAILABLE',
                'message' => 'Company API request failed: '.$e->getMessage(),
                'data' => null,
                'status' => 0,
            ];
        }

        $json = $response->json() ?? [];
        $status = $response->status();

        // SoftKatta Company routes are throttled (60/min). Homepage public GETs all force
        // verify — a 429 must use offline grace, not be recorded as INVALID_LICENSE.
        if ($status === 429 || $response->serverError()) {
            return [
                'ok' => false,
                'unavailable' => true,
                'error_code' => LicenseErrorCode::COMPANY_API_UNAVAILABLE,
                'message' => $status === 429
                    ? 'SoftKatta Company API rate limit reached. Retrying shortly.'
                    : ('SoftKatta Company API error HTTP '.$status),
                'data' => null,
                'status' => $status,
            ];
        }

        $errorCode = $json['error_code'] ?? ($response->successful() ? null : 'INVALID_LICENSE');
        $message = $json['message'] ?? $response->body();

        if ($errorCode === 'INVALID_SIGNATURE') {
            $message = 'Invalid request signature. Re-copy the SoftKatta API Secret (Reveal) for product "'.$productSlug.'" from SoftKatta admin — public key and secret must be the matching pair. Company API URL must be https://api.softkatta.in/api/v1/company';
        }

        return [
            'ok' => $response->successful() && ($json['success'] ?? false),
            'unavailable' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'data' => $json['data'] ?? null,
            'status' => $status,
        ];
    }

    /**
     * After activate, SoftKatta binds an installation to a canonical SPA domain.
     * Later verify/heartbeat must send that same host — not the API host.
     */
    private function resolveRequestDomain(string $endpoint): string
    {
        $isActivate = str_ends_with(rtrim($endpoint, '/'), '/activate');

        if (! $isActivate) {
            $bound = $this->storedBoundDomain();
            if ($bound !== null && $bound !== '') {
                return $this->normalizeDomain($bound);
            }
        }

        return $this->normalizeDomain($this->fingerprint->currentDomain());
    }

    private function storedBoundDomain(): ?string
    {
        try {
            $bound = \SoftKatta\Licensing\Models\LicenseState::query()->value('bound_domain');

            return is_string($bound) && trim($bound) !== '' ? trim($bound) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Match SoftKatta Central CompanyApiAuthService path normalization.
     */
    private function signingPath(string $base, string $endpoint): string
    {
        $basePath = (string) (parse_url($base, PHP_URL_PATH) ?: '');
        $path = rtrim($basePath, '/').'/'.ltrim($endpoint, '/');
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        if (! str_starts_with($path, '/api/')) {
            $path = '/api/'.ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Match SoftKatta Central LicenseKey::normalizeDomain for HMAC canonical string.
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        $host = explode('/', $domain)[0] ?: '';

        if ($host !== '' && str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = explode(':', $host)[0];
        }

        return strtolower($host);
    }

    private function normalizeSecret(string $secret): string
    {
        $secret = trim($secret);
        // Strip BOM / zero-width chars from copy-paste.
        $secret = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $secret) ?? $secret;

        return trim($secret);
    }

    /**
     * @return array{ok: false, unavailable: false, error_code: string, message: string, data: null, status: int}
     */
    private function configError(string $message): array
    {
        return [
            'ok' => false,
            'unavailable' => false,
            'error_code' => 'COMPANY_API_NOT_CONFIGURED',
            'message' => $message,
            'data' => null,
            'status' => 0,
        ];
    }
}
