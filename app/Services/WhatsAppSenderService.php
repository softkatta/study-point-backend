<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\WhatsAppDefaults;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppSenderService
{
    public function config(): array
    {
        return WhatsAppDefaults::configFromSection(Setting::getSection('whatsapp'));
    }

    public function isConfigured(): bool
    {
        $config = $this->config();
        $provider = $config['provider'] ?? 'interakt';

        return match ($provider) {
            'interakt' => ! empty($config['interakt_api_key']),
            'meta_cloud' => ! empty($config['meta_phone_number_id']) && ! empty($config['meta_access_token']),
            'gupshup' => ! empty($config['gupshup_api_key']) && ! empty($config['gupshup_app_name']),
            'wati' => ! empty($config['wati_access_token']),
            'twilio' => ! empty($config['twilio_account_sid']) && ! empty($config['twilio_auth_token']),
            'aisensy' => ! empty($config['aisensy_api_key']),
            default => false,
        };
    }

    public function send(string $to, string $message): void
    {
        $config = $this->config();
        $phone = $this->normalizePhone($to);
        $provider = $config['provider'] ?? 'interakt';

        match ($provider) {
            'interakt' => $this->sendViaInterakt($config, $phone, $message),
            'meta_cloud' => $this->sendViaMeta($config, $phone, $message),
            'gupshup' => $this->sendViaGupshup($config, $phone, $message),
            'wati' => $this->sendViaWati($config, $phone, $message),
            'twilio' => $this->sendViaTwilio($config, $phone, $message),
            'aisensy' => $this->sendViaAisensy($config, $phone, $message),
            default => throw new RuntimeException("Unsupported WhatsApp provider: {$provider}"),
        };
    }

    public function sendTest(string $to): void
    {
        $this->send($to, 'StudyPoint test message — your WhatsApp provider is configured correctly.');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    private function sendViaInterakt(array $config, string $phone, string $message): void
    {
        if (empty($config['interakt_api_key'])) {
            throw new RuntimeException('Interakt API key is required.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$config['interakt_api_key'],
            'Content-Type' => 'application/json',
        ])->post('https://api.interakt.ai/v1/public/message/', [
            'countryCode' => substr($phone, 0, 2),
            'phoneNumber' => substr($phone, 2),
            'type' => 'Text',
            'data' => ['message' => $message],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Interakt API error: '.$this->httpError($response));
        }
    }

    private function sendViaMeta(array $config, string $phone, string $message): void
    {
        foreach (['meta_phone_number_id', 'meta_access_token'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Meta Cloud API requires Phone Number ID and Access Token.');
            }
        }

        $response = Http::withToken($config['meta_access_token'])
            ->post("https://graph.facebook.com/v21.0/{$config['meta_phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Meta Cloud API error: '.$this->httpError($response));
        }
    }

    private function sendViaGupshup(array $config, string $phone, string $message): void
    {
        if (empty($config['gupshup_api_key'])) {
            throw new RuntimeException('Gupshup API key is required.');
        }

        $response = Http::withHeaders(['apikey' => $config['gupshup_api_key']])
            ->asForm()
            ->post('https://api.gupshup.io/wa/api/v1/msg', [
                'channel' => 'whatsapp',
                'source' => $config['gupshup_source_number'] ?: $config['phone_number'],
                'destination' => $phone,
                'message' => json_encode(['type' => 'text', 'text' => $message]),
                'src.name' => $config['gupshup_app_name'] ?: 'StudyPoint',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gupshup API error: '.$this->httpError($response));
        }
    }

    private function sendViaWati(array $config, string $phone, string $message): void
    {
        if (empty($config['wati_access_token'])) {
            throw new RuntimeException('Wati access token is required.');
        }

        $base = rtrim($config['wati_api_endpoint'] ?: 'https://live-server.wati.io', '/');
        $response = Http::withToken($config['wati_access_token'])
            ->post("{$base}/api/v1/sendSessionMessage/{$phone}", ['messageText' => $message]);

        if (! $response->successful()) {
            throw new RuntimeException('Wati API error: '.$this->httpError($response));
        }
    }

    private function sendViaTwilio(array $config, string $phone, string $message): void
    {
        foreach (['twilio_account_sid', 'twilio_auth_token', 'twilio_whatsapp_from'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Twilio requires Account SID, Auth Token and WhatsApp From number.');
            }
        }

        $sid = $config['twilio_account_sid'];
        $response = Http::withBasicAuth($sid, $config['twilio_auth_token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $config['twilio_whatsapp_from'],
                'To' => 'whatsapp:+'.$phone,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio API error: '.$this->httpError($response));
        }
    }

    private function sendViaAisensy(array $config, string $phone, string $message): void
    {
        if (empty($config['aisensy_api_key'])) {
            throw new RuntimeException('AiSensy API key is required.');
        }

        $response = Http::post('https://backend.aisensy.com/campaign/t1/api/v2', [
            'apiKey' => $config['aisensy_api_key'],
            'campaignName' => $config['aisensy_campaign_name'] ?: 'StudyPoint Notifications',
            'destination' => $phone,
            'userName' => 'StudyPoint User',
            'source' => 'StudyPoint',
            'message' => $message,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('AiSensy API error: '.$this->httpError($response));
        }
    }

    private function httpError($response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            return (string) ($json['message'] ?? $json['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
