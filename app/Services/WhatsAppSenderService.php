<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\WhatsAppDefaults;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppSenderService
{
    public function config(array $overrides = []): array
    {
        $section = $overrides === []
            ? Setting::getSection('whatsapp')
            : array_merge(Setting::getSection('whatsapp'), $overrides);

        return WhatsAppDefaults::configFromSection($section);
    }

    public function isConfigured(?array $config = null): bool
    {
        $config = $config ?? $this->config();
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

    /**
     * @return array{external_id: ?string, provider: string}
     */
    public function send(string $to, string $message, ?array $config = null): array
    {
        $config = $config ?? $this->config();
        $phone = $this->normalizePhone($to);
        if ($phone === '') {
            throw new RuntimeException('Enter a valid phone number with country code.');
        }
        $provider = $config['provider'] ?? 'interakt';

        $externalId = match ($provider) {
            'interakt' => $this->sendViaInterakt($config, $phone, $message),
            'meta_cloud' => $this->sendViaMetaText($config, $phone, $message),
            'gupshup' => $this->sendViaGupshup($config, $phone, $message),
            'wati' => $this->sendViaWati($config, $phone, $message),
            'twilio' => $this->sendViaTwilio($config, $phone, $message),
            'aisensy' => $this->sendViaAisensy($config, $phone, $message),
            default => throw new RuntimeException("Unsupported WhatsApp provider: {$provider}"),
        };

        return ['external_id' => $externalId, 'provider' => $provider];
    }

    /**
     * @param  array<int, string>  $bodyParams
     * @return array{external_id: ?string, provider: string}
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en',
        array $bodyParams = [],
        ?array $config = null,
    ): array {
        $config = $config ?? $this->config();
        $phone = $this->normalizePhone($to);
        if ($phone === '') {
            throw new RuntimeException('Enter a valid phone number with country code.');
        }

        $provider = $config['provider'] ?? 'interakt';
        $externalId = match ($provider) {
            'meta_cloud' => $this->sendViaMetaTemplate($config, $phone, $templateName, $language, $bodyParams),
            default => $this->sendViaInteraktTemplateFallback($config, $phone, $templateName, $bodyParams),
        };

        return ['external_id' => $externalId, 'provider' => $provider];
    }

    /**
     * @return array{external_id: ?string, provider: string}
     */
    public function sendDocument(
        string $to,
        string $filename,
        string $binaryContent,
        string $caption = '',
        ?array $config = null,
    ): array {
        $config = $config ?? $this->config();
        $phone = $this->normalizePhone($to);
        if ($phone === '') {
            throw new RuntimeException('Enter a valid phone number with country code.');
        }

        $provider = $config['provider'] ?? 'interakt';
        $externalId = match ($provider) {
            'meta_cloud' => $this->sendViaMetaDocument($config, $phone, $filename, $binaryContent, $caption),
            default => $this->send($phone, trim($caption."\n\nDocument: {$filename}"))['external_id'],
        };

        return ['external_id' => $externalId, 'provider' => $provider];
    }

    public function sendTest(string $to, array $configOverrides = []): void
    {
        $config = $this->config($configOverrides);

        if (! $this->isConfigured($config)) {
            throw new RuntimeException('WhatsApp provider is not configured. Enter your API credentials and try again.');
        }

        $this->send($to, 'StudyPoint test message — your WhatsApp provider is configured correctly.', $config);
    }

    public function sendOtp(string $to, string $code, ?array $config = null): array
    {
        $config = $config ?? $this->config();
        $template = trim((string) ($config['template_otp'] ?? ''));

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            return $this->sendTemplate($to, $template, 'en', [$code], $config);
        }

        return $this->send($to, "StudyPoint OTP: {$code}\n\nDo not share this code with anyone.", $config);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    private function sendViaInterakt(array $config, string $phone, string $message): ?string
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

        $this->assertSuccessful($response, 'Interakt API error');

        return $this->extractMessageId($response);
    }

    private function sendViaMetaText(array $config, string $phone, string $message): ?string
    {
        $this->assertMetaConfigured($config);

        $response = Http::withToken($config['meta_access_token'])
            ->post($this->metaMessagesUrl($config), [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        $this->assertSuccessful($response, 'Meta Cloud API error');

        return $this->extractMessageId($response);
    }

    /**
     * @param  array<int, string>  $bodyParams
     */
    private function sendViaMetaTemplate(
        array $config,
        string $phone,
        string $templateName,
        string $language,
        array $bodyParams,
    ): ?string {
        $this->assertMetaConfigured($config);

        $components = [];
        if ($bodyParams !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_values(array_map(
                    fn (string $text) => ['type' => 'text', 'text' => $text],
                    $bodyParams
                )),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($config['meta_access_token'])
            ->post($this->metaMessagesUrl($config), $payload);

        $this->assertSuccessful($response, 'Meta Cloud template error');

        return $this->extractMessageId($response);
    }

    private function sendViaMetaDocument(
        array $config,
        string $phone,
        string $filename,
        string $binaryContent,
        string $caption,
    ): ?string {
        $this->assertMetaConfigured($config);

        $mediaId = $this->uploadMetaMedia($config, $filename, $binaryContent, 'application/pdf');

        $response = Http::withToken($config['meta_access_token'])
            ->post($this->metaMessagesUrl($config), [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => $filename,
                    'caption' => $caption !== '' ? $caption : null,
                ],
            ]);

        $this->assertSuccessful($response, 'Meta Cloud document error');

        return $this->extractMessageId($response);
    }

    private function uploadMetaMedia(array $config, string $filename, string $content, string $mime): string
    {
        $response = Http::withToken($config['meta_access_token'])
            ->attach('file', $content, $filename, ['Content-Type' => $mime])
            ->post("https://graph.facebook.com/v21.0/{$config['meta_phone_number_id']}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mime,
            ]);

        $this->assertSuccessful($response, 'Meta Cloud media upload error');

        $mediaId = $response->json('id');
        if (! is_string($mediaId) || $mediaId === '') {
            throw new RuntimeException('Meta Cloud media upload did not return a media ID.');
        }

        return $mediaId;
    }

    /**
     * @param  array<int, string>  $bodyParams
     */
    private function sendViaInteraktTemplateFallback(
        array $config,
        string $phone,
        string $templateName,
        array $bodyParams,
    ): ?string {
        $message = $templateName;
        if ($bodyParams !== []) {
            $message .= ': '.implode(' | ', $bodyParams);
        }

        return $this->sendViaInterakt($config, $phone, $message);
    }

    private function sendViaGupshup(array $config, string $phone, string $message): ?string
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

        $this->assertSuccessful($response, 'Gupshup API error');

        return $this->extractMessageId($response);
    }

    private function sendViaWati(array $config, string $phone, string $message): ?string
    {
        if (empty($config['wati_access_token'])) {
            throw new RuntimeException('Wati access token is required.');
        }

        $base = rtrim($config['wati_api_endpoint'] ?: 'https://live-server.wati.io', '/');
        $response = Http::withToken($config['wati_access_token'])
            ->post("{$base}/api/v1/sendSessionMessage/{$phone}", ['messageText' => $message]);

        $this->assertSuccessful($response, 'Wati API error');

        return $this->extractMessageId($response);
    }

    private function sendViaTwilio(array $config, string $phone, string $message): ?string
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

        $this->assertSuccessful($response, 'Twilio API error');

        return $response->json('sid');
    }

    private function sendViaAisensy(array $config, string $phone, string $message): ?string
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

        $this->assertSuccessful($response, 'AiSensy API error');

        return $this->extractMessageId($response);
    }

    private function assertMetaConfigured(array $config): void
    {
        foreach (['meta_phone_number_id', 'meta_access_token'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Meta Cloud API requires Phone Number ID and Access Token.');
            }
        }
    }

    private function metaMessagesUrl(array $config): string
    {
        return "https://graph.facebook.com/v21.0/{$config['meta_phone_number_id']}/messages";
    }

    private function assertSuccessful(Response $response, string $prefix): void
    {
        if (! $response->successful()) {
            throw new RuntimeException($prefix.': '.$this->httpError($response));
        }
    }

    private function extractMessageId(Response $response): ?string
    {
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $id = $json['messages'][0]['id'] ?? $json['id'] ?? $json['messageId'] ?? null;

        return is_string($id) ? $id : null;
    }

    private function httpError(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $error = $json['error']['message'] ?? $json['message'] ?? $json['error'] ?? null;
            if (is_string($error)) {
                return $error;
            }
        }

        return (string) $response->body();
    }
}
