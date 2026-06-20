<?php

namespace App\Support;

class TopBarDefaults
{
    public static function all(): array
    {
        return [
            'status_badge_text' => 'Open Now',
            'status_badge_visible' => true,
            'operating_hours_visible' => true,
            'branches_label_template' => '{count} Branches',
            'branches_href' => '/branches',
            'mobile_call_label' => 'Call Us',
            'whatsapp_label' => 'WhatsApp',
            'whatsapp_number' => '',
            'enroll_label' => 'Enroll',
            'enroll_href' => '/admission',
            'social_visible' => true,
        ];
    }

    public static function merge(array $stored): array
    {
        $merged = array_merge(self::all(), $stored);

        foreach (['status_badge_visible', 'operating_hours_visible', 'social_visible'] as $flag) {
            if (array_key_exists($flag, $merged)) {
                $merged[$flag] = filter_var($merged[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $merged;
    }

    public static function validationRules(): array
    {
        return [
            'status_badge_text' => ['nullable', 'string', 'max:60'],
            'status_badge_visible' => ['nullable', 'boolean'],
            'operating_hours_visible' => ['nullable', 'boolean'],
            'branches_label_template' => ['nullable', 'string', 'max:80'],
            'branches_href' => ['nullable', 'string', 'max:200'],
            'mobile_call_label' => ['nullable', 'string', 'max:40'],
            'whatsapp_label' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'enroll_label' => ['nullable', 'string', 'max:40'],
            'enroll_href' => ['nullable', 'string', 'max:200'],
            'social_visible' => ['nullable', 'boolean'],
        ];
    }
}
