<?php

namespace App\Support;

class CompanyDefaults
{
    public static function all(): array
    {
        return [
            'legal_name' => 'StudyPoint Learning Spaces Pvt. Ltd.',
            'trade_name' => 'StudyPoint',
            'email' => 'hello@studypoint.in',
            'phone' => '+91 98765 43210',
            'address' => '123 Knowledge Park, Education City, Mumbai - 400001',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'website' => 'https://studypoint.in',
            'cin' => '',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }
}
