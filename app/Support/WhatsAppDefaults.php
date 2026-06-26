<?php

namespace App\Support;

class WhatsAppDefaults
{
    public static function all(): array
    {
        return [
            'provider' => 'interakt',
            'phone_number' => '+919876543210',
            'interakt_api_key' => '',
            'meta_phone_number_id' => '',
            'meta_waba_id' => '',
            'meta_access_token' => '',
            'gupshup_api_key' => '',
            'gupshup_app_name' => '',
            'gupshup_source_number' => '',
            'wati_api_endpoint' => 'https://live-server.wati.io',
            'wati_access_token' => '',
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_whatsapp_from' => 'whatsapp:+14155238886',
            'aisensy_api_key' => '',
            'aisensy_campaign_name' => '',
            'notify_admission' => true,
            'notify_payment' => true,
            'notify_renewal_7d' => true,
            'notify_renewal_1d' => true,
            'notify_attendance' => true,
            'meta_webhook_verify_token' => '',
            'template_payment_success' => 'studypoint_payment_success',
            'template_order_confirmation' => 'studypoint_order_confirmation',
            'template_otp' => 'studypoint_otp',
            'template_invoice' => 'studypoint_invoice',
            'template_renewal_7d' => 'studypoint_renewal_7d',
            'template_renewal_1d' => 'studypoint_renewal_1d',
            'template_attendance' => 'studypoint_attendance',
        ];
    }

    public static function merge(array $stored): array
    {
        $defaults = self::all();
        $merged = [];
        foreach ($defaults as $key => $default) {
            $merged[$key] = $stored[$key] ?? $default;
        }

        return $merged;
    }

    public static function validationRules(): array
    {
        return [
            'provider' => ['required', 'in:interakt,meta_cloud,gupshup,wati,twilio,aisensy'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'interakt_api_key' => ['nullable', 'string', 'max:500'],
            'meta_phone_number_id' => ['nullable', 'string', 'max:100'],
            'meta_waba_id' => ['nullable', 'string', 'max:100'],
            'meta_access_token' => ['nullable', 'string', 'max:1000'],
            'gupshup_api_key' => ['nullable', 'string', 'max:500'],
            'gupshup_app_name' => ['nullable', 'string', 'max:100'],
            'gupshup_source_number' => ['nullable', 'string', 'max:20'],
            'wati_api_endpoint' => ['nullable', 'string', 'max:300'],
            'wati_access_token' => ['nullable', 'string', 'max:500'],
            'twilio_account_sid' => ['nullable', 'string', 'max:100'],
            'twilio_auth_token' => ['nullable', 'string', 'max:500'],
            'twilio_whatsapp_from' => ['nullable', 'string', 'max:30'],
            'aisensy_api_key' => ['nullable', 'string', 'max:500'],
            'aisensy_campaign_name' => ['nullable', 'string', 'max:100'],
            'notify_admission' => ['nullable', 'boolean'],
            'notify_payment' => ['nullable', 'boolean'],
            'notify_renewal_7d' => ['nullable', 'boolean'],
            'notify_renewal_1d' => ['nullable', 'boolean'],
            'notify_attendance' => ['nullable', 'boolean'],
            'meta_webhook_verify_token' => ['nullable', 'string', 'max:100'],
            'template_payment_success' => ['nullable', 'string', 'max:100'],
            'template_order_confirmation' => ['nullable', 'string', 'max:100'],
            'template_otp' => ['nullable', 'string', 'max:100'],
            'template_invoice' => ['nullable', 'string', 'max:100'],
            'template_renewal_7d' => ['nullable', 'string', 'max:100'],
            'template_renewal_1d' => ['nullable', 'string', 'max:100'],
            'template_attendance' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $data): array
    {
        $stringFields = [
            'provider', 'phone_number', 'interakt_api_key', 'meta_phone_number_id', 'meta_waba_id',
            'meta_access_token', 'gupshup_api_key', 'gupshup_app_name', 'gupshup_source_number',
            'wati_api_endpoint', 'wati_access_token', 'twilio_account_sid', 'twilio_auth_token',
            'twilio_whatsapp_from', 'aisensy_api_key', 'aisensy_campaign_name',
            'meta_webhook_verify_token', 'template_payment_success', 'template_order_confirmation',
            'template_otp', 'template_invoice', 'template_renewal_7d', 'template_renewal_1d', 'template_attendance',
        ];

        foreach ($stringFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && ! is_string($data[$field])) {
                $data[$field] = (string) $data[$field];
            }
        }

        return $data;
    }

    public static function configFromSection(array $section): array
    {
        $config = [];
        foreach (array_keys(self::all()) as $key) {
            if (array_key_exists($key, $section)) {
                $config[$key] = $section[$key];
            }
        }

        return self::merge($config);
    }
}
