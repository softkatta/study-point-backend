<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private AdmissionService $admissions,
        private SubscriptionService $subscriptions,
        private InvoiceService $invoices,
        private NotificationDispatchService $notifications,
        private PaymentGatewayService $gateway,
    ) {}

    public function markPaid(Payment $payment, array $meta = []): Payment
    {
        $payment = DB::transaction(function () use ($payment, $meta) {
            $paidAt = isset($meta['paid_at'])
                ? ($meta['paid_at'] instanceof Carbon ? $meta['paid_at'] : Carbon::parse($meta['paid_at']))
                : now();

            $payment->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'transaction_id' => $meta['transaction_id'] ?? $payment->transaction_id,
                'method' => $meta['method'] ?? $payment->method,
            ]);

            $payment = $payment->fresh(['student', 'admission']);

            if ($payment->admission_id && $payment->admission) {
                $payment->admission->update([
                    'payment_status' => 'paid',
                    'payment_date' => $paidAt->toDateString(),
                    'payment_mode' => $this->modeFromMethod($payment->method),
                    'transaction_id' => $payment->transaction_id,
                ]);
                $this->admissions->syncPaymentRecord($payment->admission->fresh());

                $admission = $payment->admission->fresh();
                $this->admissions->finalizeAfterPayment($admission);
                $payment = $payment->fresh(['student', 'admission', 'subscription']);
            }

            $this->linkMembershipIds($payment);
            $payment = $payment->fresh(['student', 'admission', 'subscription']);

            if ($payment->admission_id) {
                $this->subscriptions->activateMembership($payment->student, $payment->subscription);
            } elseif ($payment->subscription_id) {
                $this->subscriptions->completeRenewalOnPayment($payment);
            }

            return $payment->fresh(['student', 'admission', 'subscription']);
        });

        if ($payment->student_id) {
            $this->deferPostPaymentSideEffects($payment->id);
        }

        return $payment;
    }

    private function deferPostPaymentSideEffects(int $paymentId): void
    {
        dispatch(function () use ($paymentId) {
            $payment = Payment::with(['student.admission', 'admission'])->find($paymentId);
            if (! $payment?->student_id) {
                return;
            }

            try {
                app(InvoiceService::class)->maybeAutoGenerate($payment);
            } catch (\Throwable) {
                // Payment is recorded; invoice generation is best-effort
            }

            try {
                app(NotificationDispatchService::class)->paymentReceipt(
                    $payment->fresh(['student.admission', 'admission'])
                );
            } catch (\Throwable) {
                // Payment is recorded; notifications are best-effort
            }
        })->afterResponse();
    }

    public function activationMeta(Payment $payment): array
    {
        $payment->loadMissing(['student', 'admission.student']);
        $student = $payment->student ?? $payment->admission?->student;
        $email = $student?->email;

        return [
            'portal_ready' => (bool) $student?->user_id,
            'credentials_sent' => (bool) ($email && $student?->user_id),
            'credentials_email' => $email,
        ];
    }

    public function collectForAdmission(Admission $admission, array $data): Payment
    {
        $this->gateway->assertCounterCollection((string) ($data['method'] ?? ''));

        $payment = Payment::where('admission_id', $admission->id)->first();

        if (! $payment) {
            $this->admissions->syncPaymentRecord($admission);
            $payment = Payment::where('admission_id', $admission->id)->first();
        }

        if (! $payment) {
            throw new \RuntimeException('No payment due for this admission.');
        }

        if ($payment->status === 'paid') {
            throw new \RuntimeException('Payment already collected for this admission.');
        }

        return $this->markPaid($payment, [
            'method' => $data['method'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'paid_at' => isset($data['payment_date']) ? Carbon::parse($data['payment_date']) : now(),
        ]);
    }

    public function collectAtCounter(Payment $payment, array $data): Payment
    {
        $this->gateway->assertCounterCollection((string) ($data['method'] ?? ''));

        if ($payment->status === 'paid') {
            throw new \RuntimeException('Payment already collected.');
        }

        if ($this->gateway->isOnlineMethod((string) $payment->method)) {
            throw new \RuntimeException('Online payments must be completed via the payment gateway.');
        }

        return $this->markPaid($payment, [
            'method' => $data['method'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'paid_at' => isset($data['payment_date']) ? Carbon::parse($data['payment_date']) : now(),
        ]);
    }

    private function linkMembershipIds(Payment $payment): void
    {
        $updates = [];

        if (! $payment->student_id && $payment->admission?->student_id) {
            $updates['student_id'] = $payment->admission->student_id;
        }

        if (! $payment->subscription_id && $payment->admission?->subscription_id) {
            $updates['subscription_id'] = $payment->admission->subscription_id;
        }

        if ($updates !== []) {
            $payment->update($updates);
        }
    }

    private function modeFromMethod(?string $method): ?string
    {
        return match (strtolower((string) $method)) {
            'upi' => 'upi',
            'card' => 'card',
            'net banking', 'netbanking' => 'netbanking',
            'cash' => 'cash',
            'check', 'cheque' => 'check',
            default => $method ? strtolower($method) : null,
        };
    }

    public function markRefundReceived(Payment $payment, array $meta = []): Payment
    {
        if ($payment->refund_status !== 'pending') {
            throw new \RuntimeException('This payment has no pending refund to mark as received.');
        }

        $payment->update([
            'refund_status' => 'received',
            'status' => 'refunded',
            'refunded_at' => isset($meta['refunded_at'])
                ? Carbon::parse($meta['refunded_at'])
                : now(),
            'transaction_id' => $meta['transaction_id'] ?? $payment->transaction_id,
            'method' => $meta['method'] ?? $payment->method,
        ]);

        app(InvoiceService::class)->markRefundInvoicePaid($payment->fresh());

        return $payment->fresh(['student', 'admission', 'subscription']);
    }
}
