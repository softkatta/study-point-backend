<?php

namespace App\Support;

class SecurityDefaults
{
    public static function all(): array
    {
        return [
            'session_timeout_minutes' => 480,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'require_2fa_admins' => false,
            'require_strong_password' => true,
            'min_password_length' => 8,
            'password_expiry_days' => 0,
            'allow_student_self_register' => false,
            'force_https' => true,
            'ip_whitelist_enabled' => false,
            'ip_whitelist' => '',
            'audit_log_retention_days' => 90,
            'single_session_per_user' => false,
            'api_rate_limit_per_minute' => 120,
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'session_timeout_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
            'max_login_attempts' => ['nullable', 'integer', 'min:3', 'max:20'],
            'lockout_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'require_2fa_admins' => ['nullable', 'boolean'],
            'require_strong_password' => ['nullable', 'boolean'],
            'min_password_length' => ['nullable', 'integer', 'min:6', 'max:32'],
            'password_expiry_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'allow_student_self_register' => ['nullable', 'boolean'],
            'force_https' => ['nullable', 'boolean'],
            'ip_whitelist_enabled' => ['nullable', 'boolean'],
            'ip_whitelist' => ['nullable', 'string', 'max:2000'],
            'audit_log_retention_days' => ['nullable', 'integer', 'min:7', 'max:3650'],
            'single_session_per_user' => ['nullable', 'boolean'],
            'api_rate_limit_per_minute' => ['nullable', 'integer', 'min:30', 'max:1000'],
        ];
    }
}
