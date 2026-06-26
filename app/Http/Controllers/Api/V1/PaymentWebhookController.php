<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    public function razorpay(
        Request $request,
        PaymentGatewayService $gateway,
        PaymentService $payments,
    ): Response {
        $config = $gateway->config();
        $secret = (string) ($config['razorpay_webhook_secret'] ?? '');

        if ($secret !== '') {
            $signature = (string) $request->header('X-Razorpay-Signature', '');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, $signature)) {
                return response('Invalid signature', 400);
            }
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');

        if ($event !== 'payment.captured') {
            return response('Ignored', 200);
        }

        $entity = $payload['payload']['payment']['entity'] ?? [];
        $paymentId = (string) ($entity['id'] ?? '');
        $orderId = (string) ($entity['order_id'] ?? '');
        $receipt = (string) ($entity['notes']['payment_code'] ?? $entity['receipt'] ?? '');

        $payment = Payment::query()
            ->where('status', '!=', 'paid')
            ->where(function ($query) use ($paymentId, $orderId, $receipt) {
                if ($paymentId !== '') {
                    $query->orWhere('transaction_id', $paymentId);
                }
                if ($orderId !== '') {
                    $query->orWhere('transaction_id', $orderId);
                }
                if ($receipt !== '') {
                    $query->orWhere('payment_code', $receipt);
                }
            })
            ->first();

        if (! $payment) {
            return response('Payment not found', 200);
        }

        $payments->markPaid($payment, [
            'transaction_id' => $paymentId ?: $payment->transaction_id,
            'method' => 'razorpay',
            'paid_at' => now(),
        ]);

        return response('OK', 200);
    }
}
