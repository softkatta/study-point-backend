<?php

namespace App\Support;

class GstDefaults
{
    public static function all(): array
    {
        return [
            'gstin' => '27AABCS1429B1ZB',
            'pan' => 'AABCS1429B',
            'state_code' => '27',
            'state_name' => 'Maharashtra',
            'gst_rate' => 18,
            'cgst_rate' => 9,
            'sgst_rate' => 9,
            'igst_rate' => 18,
            'registration_type' => 'regular',
            'filing_frequency' => 'monthly',
            'reverse_charge' => false,
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'gstin' => ['nullable', 'string', 'max:15'],
            'pan' => ['nullable', 'string', 'max:10'],
            'state_code' => ['nullable', 'string', 'max:5'],
            'state_name' => ['nullable', 'string', 'max:50'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'sgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'igst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'registration_type' => ['nullable', 'in:regular,composition,unregistered'],
            'filing_frequency' => ['nullable', 'in:monthly,quarterly'],
            'reverse_charge' => ['nullable', 'boolean'],
        ];
    }
}
