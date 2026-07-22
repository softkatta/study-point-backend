<?php

namespace App\Support;

class PaymentDefaults
{
    public static function all(): array
    {
        return [
            'provider' => 'razorpay',
            'enabled' => false,
            'test_mode' => false,
            'razorpay_key_id' => '',
            'razorpay_key_secret' => '',
            'razorpay_webhook_secret' => '',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            'payu_merchant_key' => '',
            'payu_merchant_salt' => '',
            'cashfree_app_id' => '',
            'cashfree_secret_key' => '',
            'phonepe_merchant_id' => '',
            'phonepe_salt_key' => '',
            'phonepe_salt_index' => '1',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'provider' => ['required', 'in:razorpay'],
            'enabled' => ['nullable', 'boolean'],
            'test_mode' => ['nullable', 'boolean'],
            'razorpay_key_id' => ['nullable', 'string', 'max:200'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:500'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:500'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:200'],
            'stripe_secret_key' => ['nullable', 'string', 'max:500'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:500'],
            'payu_merchant_key' => ['nullable', 'string', 'max:200'],
            'payu_merchant_salt' => ['nullable', 'string', 'max:500'],
            'cashfree_app_id' => ['nullable', 'string', 'max:200'],
            'cashfree_secret_key' => ['nullable', 'string', 'max:500'],
            'phonepe_merchant_id' => ['nullable', 'string', 'max:200'],
            'phonepe_salt_key' => ['nullable', 'string', 'max:500'],
            'phonepe_salt_index' => ['nullable', 'string', 'max:10'],
        ];
    }
}
