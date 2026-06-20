<?php

namespace App\Support;

class GeneralDefaults
{
    public static function all(): array
    {
        return [
            'support_email' => 'support@studypoint.in',
            'support_phone' => '+91 98765 43210',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'language' => 'en',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'support_email' => ['nullable', 'email', 'max:150'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'language' => ['nullable', 'string', 'max:10'],
        ];
    }
}
