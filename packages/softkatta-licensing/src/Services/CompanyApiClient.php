<?php

namespace SoftKatta\Licensing\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SoftKatta\Licensing\Support\HmacSigner;

class CompanyApiClient
{
    public function __construct(private ServerFingerprintService $fingerprint) {}

    public function activate(string $licenseKey, ?string $installationId = null): array
    {
        return $this->request('POST', '/activate', [
            'license_key' => $licenseKey,
            'installation_id' => $installationId,
        ], includeInstallToken: false);
    }

    public function verify(string $installToken, string $installationId): array
    {
        return $this->request('POST', '/verify', [], $installToken, $installationId);
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

    public function heartbeat(string $installToken, string $installationId): array
    {
        return $this->request('POST', '/heartbeat', [], $installToken, $installationId);
    }

    private function request(
        string $method,
        string $path,
        array $body = [],
        ?string $installToken = null,
        ?string $installationId = null,
        bool $includeInstallToken = true,
    ): array {
        $base = config('softkatta.company_api_url');
        $publicKey = (string) config('softkatta.public_api_key');
        $secret = (string) config('softkatta.api_secret');

        if ($publicKey === '' || $secret === '') {
            throw new RuntimeException('SoftKatta API credentials are not configured.');
        }

        if (
            config('softkatta.require_https')
            && ! app()->environment(['local', 'testing'])
            && ! str_starts_with((string) $base, 'https://')
        ) {
            throw new RuntimeException('Company API URL must use HTTPS in production.');
        }

        $domain = $this->fingerprint->currentDomain();
        $fp = $this->fingerprint->generate();
        $productSlug = (string) config('softkatta.product_slug');
        $productVersion = (string) config('softkatta.product_version');
        $installationId = $installationId ?? '';
        $timestamp = (string) time();
        $nonce = Str::random(32);

        $url = $base.$path;
        $parsedPath = parse_url($url, PHP_URL_PATH) ?: $path;
        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? ''
            : json_encode($body, JSON_UNESCAPED_SLASHES);

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
            $rawBody ?: '',
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
            $pending = Http::withHeaders($headers)->timeout(20);
            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->withBody($rawBody ?: '{}', 'application/json')->post($url),
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
        }

        $json = $response->json() ?? [];

        return [
            'ok' => $response->successful() && ($json['success'] ?? false),
            'unavailable' => false,
            'error_code' => $json['error_code'] ?? ($response->successful() ? null : 'INVALID_LICENSE'),
            'message' => $json['message'] ?? $response->body(),
            'data' => $json['data'] ?? null,
            'status' => $response->status(),
        ];
    }
}
