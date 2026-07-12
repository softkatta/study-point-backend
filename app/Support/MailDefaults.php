<?php

namespace App\Support;

class MailDefaults
{
    public static function all(): array
    {
        return [
            'provider' => 'smtp',
            'from_email' => 'noreply@studypoint.in',
            'from_name' => 'StudyPoint',
            'reply_to' => 'support@studypoint.in',
            'notify_parent_email' => true,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'brevo_api_key' => '',
            'resend_api_key' => '',
            'ms_tenant_id' => '',
            'ms_client_id' => '',
            'ms_client_secret' => '',
            'ms_from_user' => '',
            'gmail_client_id' => '',
            'gmail_client_secret' => '',
            'gmail_refresh_token' => '',
            'gmail_user_email' => '',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'provider' => ['required', 'in:smtp,brevo,resend,microsoft_graph,gmail_api'],
            'from_email' => ['nullable', 'email', 'max:150'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'reply_to' => ['nullable', 'email', 'max:150'],
            'notify_parent_email' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:200'],
            'smtp_port' => ['nullable', 'string', 'max:10'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:200'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'brevo_api_key' => ['nullable', 'string', 'max:500'],
            'resend_api_key' => ['nullable', 'string', 'max:500'],
            'ms_tenant_id' => ['nullable', 'string', 'max:100'],
            'ms_client_id' => ['nullable', 'string', 'max:100'],
            'ms_client_secret' => ['nullable', 'string', 'max:500'],
            'ms_from_user' => ['nullable', 'string', 'max:200'],
            'gmail_client_id' => ['nullable', 'string', 'max:200'],
            'gmail_client_secret' => ['nullable', 'string', 'max:500'],
            'gmail_refresh_token' => ['nullable', 'string', 'max:1000'],
            'gmail_user_email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
