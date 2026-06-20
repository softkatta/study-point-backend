<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\PaymentDefaults;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymentGatewayService
{
    public function config(): array
    {
        return PaymentDefaults::merge(Setting::getSection('payment'));
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config()['enabled'] ?? false);
    }

    public function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Payment gateway is disabled. Enable it in Settings → Payment Gateway.');
        }
    }

    public function isOnlineMethod(string $method): bool
    {
        $online = ['razorpay', 'stripe', 'payu', 'cashfree', 'phonepe', 'online', 'upi', 'card', 'netbanking'];

        return in_array(strtolower($method), $online, true);
    }

    public function isCounterMethod(string $method): bool
    {
        return in_array(strtolower($method), ['cash', 'check', 'cheque'], true);
    }

    public function assertCounterCollection(string $method): void
    {
        if ($this->isOnlineMethod($method)) {
            throw new RuntimeException('Online payments are collected automatically via the payment gateway.');
        }

        if (! $this->isCounterMethod($method)) {
            throw new RuntimeException('Only cash or check payments can be recorded at the counter.');
        }
    }

    /** Public keys safe for frontend checkout */
    public function checkoutConfig(): array
    {
        $config = $this->config();
        $provider = $config['provider'] ?? 'razorpay';

        $public = [
            'enabled' => $this->isEnabled(),
            'provider' => $provider,
            'test_mode' => (bool) ($config['test_mode'] ?? true),
        ];

        return match ($provider) {
            'razorpay' => array_merge($public, [
                'key_id' => $config['razorpay_key_id'] ?? '',
            ]),
            'stripe' => array_merge($public, [
                'publishable_key' => $config['stripe_publishable_key'] ?? '',
            ]),
            'cashfree' => array_merge($public, [
                'app_id' => $config['cashfree_app_id'] ?? '',
            ]),
            default => $public,
        };
    }

    public function verifyRazorpaySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->config()['razorpay_key_secret'] ?? '';
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function createOrder(float $amount, string $receipt, string $currency = 'INR'): array
    {
        $this->assertEnabled();
        $config = $this->config();
        $provider = $config['provider'] ?? 'razorpay';
        $amountPaise = (int) round($amount * 100);

        return match ($provider) {
            'razorpay' => $this->createRazorpayOrder($config, $amountPaise, $receipt, $currency),
            'stripe' => $this->createStripeIntent($config, $amountPaise, $currency),
            default => [
                'provider' => $provider,
                'amount' => $amount,
                'currency' => $currency,
                'receipt' => $receipt,
                'message' => 'Use provider SDK on frontend; credentials are configured.',
            ],
        };
    }

    private function createRazorpayOrder(array $config, int $amountPaise, string $receipt, string $currency): array
    {
        if (empty($config['razorpay_key_id']) || empty($config['razorpay_key_secret'])) {
            throw new \RuntimeException('Razorpay credentials are not configured.');
        }

        $response = Http::withBasicAuth($config['razorpay_key_id'], $config['razorpay_key_secret'])
            ->timeout(15)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => $currency,
                'receipt' => $receipt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Razorpay order error: '.$this->httpError($response));
        }

        $order = $response->json();

        return [
            'provider' => 'razorpay',
            'order_id' => $order['id'] ?? null,
            'amount' => $order['amount'] ?? $amountPaise,
            'currency' => $order['currency'] ?? $currency,
            'key_id' => $config['razorpay_key_id'],
        ];
    }

    private function createStripeIntent(array $config, int $amountPaise, string $currency): array
    {
        if (empty($config['stripe_secret_key'])) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $response = Http::withToken($config['stripe_secret_key'])
            ->asForm()
            ->timeout(15)
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amountPaise,
                'currency' => strtolower($currency),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Stripe intent error: '.$this->httpError($response));
        }

        $intent = $response->json();

        return [
            'provider' => 'stripe',
            'client_secret' => $intent['client_secret'] ?? null,
            'publishable_key' => $config['stripe_publishable_key'] ?? '',
        ];
    }

    public function testConnection(): array
    {
        $config = $this->config();
        $provider = $config['provider'] ?? 'razorpay';

        if (! ($config['enabled'] ?? false)) {
            throw new RuntimeException('Enable payment gateway before testing.');
        }

        return match ($provider) {
            'razorpay' => $this->testRazorpay($config),
            'stripe' => $this->testStripe($config),
            'payu' => $this->testPayu($config),
            'cashfree' => $this->testCashfree($config),
            'phonepe' => $this->testPhonepe($config),
            default => throw new RuntimeException("Unsupported payment provider: {$provider}"),
        };
    }

    private function testRazorpay(array $config): array
    {
        if (empty($config['razorpay_key_id']) || empty($config['razorpay_key_secret'])) {
            throw new RuntimeException('Razorpay Key ID and Key Secret are required.');
        }

        $response = Http::withBasicAuth($config['razorpay_key_id'], $config['razorpay_key_secret'])
            ->timeout(15)
            ->get('https://api.razorpay.com/v1/orders', ['count' => 1]);

        if (! $response->successful()) {
            throw new RuntimeException('Razorpay API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'razorpay', 'test_mode' => (bool) ($config['test_mode'] ?? true)];
    }

    private function testStripe(array $config): array
    {
        if (empty($config['stripe_secret_key'])) {
            throw new RuntimeException('Stripe Secret Key is required.');
        }

        $response = Http::withToken($config['stripe_secret_key'])
            ->timeout(15)
            ->get('https://api.stripe.com/v1/balance');

        if (! $response->successful()) {
            throw new RuntimeException('Stripe API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'stripe', 'test_mode' => str_starts_with($config['stripe_secret_key'], 'sk_test_')];
    }

    private function testPayu(array $config): array
    {
        if (empty($config['payu_merchant_key']) || empty($config['payu_merchant_salt'])) {
            throw new RuntimeException('PayU Merchant Key and Salt are required.');
        }

        return [
            'ok' => true,
            'provider' => 'payu',
            'test_mode' => (bool) ($config['test_mode'] ?? true),
            'message' => 'PayU credentials saved. Live verification happens on first payment.',
        ];
    }

    private function testCashfree(array $config): array
    {
        if (empty($config['cashfree_app_id']) || empty($config['cashfree_secret_key'])) {
            throw new RuntimeException('Cashfree App ID and Secret Key are required.');
        }

        $testMode = (bool) ($config['test_mode'] ?? true);
        $base = $testMode ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';

        $response = Http::timeout(15)
            ->withHeaders([
                'x-client-id' => $config['cashfree_app_id'],
                'x-client-secret' => $config['cashfree_secret_key'],
                'x-api-version' => '2023-08-01',
            ])
            ->get("{$base}/pg/orders", ['order_status' => 'ACTIVE', 'order_limit' => 1]);

        if (! $response->successful() && ! in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('Cashfree API error: '.$this->httpError($response));
        }

        return ['ok' => true, 'provider' => 'cashfree', 'test_mode' => $testMode];
    }

    private function testPhonepe(array $config): array
    {
        foreach (['phonepe_merchant_id', 'phonepe_salt_key'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('PhonePe Merchant ID and Salt Key are required.');
            }
        }

        return [
            'ok' => true,
            'provider' => 'phonepe',
            'test_mode' => (bool) ($config['test_mode'] ?? true),
            'message' => 'PhonePe credentials saved. Live verification happens on first payment.',
        ];
    }

    private function httpError($response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            return (string) ($body['message'] ?? $body['error']['description'] ?? $body['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
