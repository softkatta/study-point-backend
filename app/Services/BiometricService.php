<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\BiometricDefaults;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BiometricService
{
    public function __construct(private SmartOfficeService $smartOffice) {}

    public function config(): array
    {
        return BiometricDefaults::merge(Setting::getSection('biometric'));
    }

    public function smartOffice(): SmartOfficeService
    {
        return $this->smartOffice;
    }

    public function testConnection(): array
    {
        $config = $this->config();
        $provider = $config['provider'] ?? 'smartoffice';

        if ($provider === 'manual') {
            return ['ok' => true, 'provider' => 'manual', 'message' => 'Manual / demo mode — no live API connection required.'];
        }

        if (! ($config['enabled'] ?? false)) {
            throw new RuntimeException('Enable biometric integration before testing.');
        }

        return match ($provider) {
            'smartoffice' => $this->smartOffice->testConnection(),
            'zkteco' => $this->testZkteco($config),
            'essl' => $this->testEssl($config),
            'hikvision' => $this->testHikvision($config),
            'custom_api' => $this->testCustomApi($config),
            default => throw new RuntimeException("Unsupported biometric provider: {$provider}"),
        };
    }

    private function testZkteco(array $config): array
    {
        if (empty($config['zkteco_server_ip'])) {
            throw new RuntimeException('ZKTeco server IP is required.');
        }

        $port = $config['zkteco_port'] ?? '4370';
        $host = $config['zkteco_server_ip'];

        $response = Http::timeout(5)->get("http://{$host}:{$port}");

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException("Could not reach ZKTeco server at {$host}:{$port}. Verify IP, port and firewall.");
        }

        return ['ok' => true, 'provider' => 'zkteco', 'message' => 'ZKTeco server is reachable.'];
    }

    private function testEssl(array $config): array
    {
        if (empty($config['essl_server_url']) || empty($config['essl_api_key'])) {
            throw new RuntimeException('eSSL server URL and API key are required.');
        }

        $base = rtrim((string) $config['essl_server_url'], '/');
        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => 'Bearer '.$config['essl_api_key']])
            ->get("{$base}/api/v1/devices");

        if (! $response->successful() && ! in_array($response->status(), [401, 403, 404], true)) {
            throw new RuntimeException('eSSL API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'essl'];
    }

    private function testHikvision(array $config): array
    {
        foreach (['hikvision_server_url', 'hikvision_app_key', 'hikvision_app_secret'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Hikvision server URL, App Key and App Secret are required.');
            }
        }

        $base = rtrim((string) $config['hikvision_server_url'], '/');
        $response = Http::timeout(15)
            ->withHeaders([
                'X-Ca-Key' => $config['hikvision_app_key'],
                'X-Ca-Signature' => hash_hmac('sha256', '', $config['hikvision_app_secret']),
            ])
            ->get("{$base}/artemis/api/resource/v1/acsDevice/acsDeviceList");

        if (! $response->successful() && ! in_array($response->status(), [401, 403, 404], true)) {
            throw new RuntimeException('Hikvision API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'hikvision'];
    }

    private function testCustomApi(array $config): array
    {
        if (empty($config['custom_api_base_url'])) {
            throw new RuntimeException('Custom API base URL is required.');
        }

        $base = rtrim((string) $config['custom_api_base_url'], '/');
        $header = $config['custom_api_auth_header'] ?? 'Authorization';
        $request = Http::timeout(15);

        if (! empty($config['custom_api_key'])) {
            $request = $request->withHeaders([$header => 'Bearer '.$config['custom_api_key']]);
        }

        $response = $request->get($base);

        if (! $response->successful() && ! in_array($response->status(), [401, 403, 404, 405], true)) {
            throw new RuntimeException('Custom API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'custom_api'];
    }

    private function httpError(Response $response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            return (string) ($body['message'] ?? $body['Message'] ?? $body['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
