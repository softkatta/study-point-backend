<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;

class InvoiceService
{
    public function __construct(
        private AppSettingsService $settings,
        private NotificationDispatchService $notifications,
    ) {}

    public function nextInvoiceCode(): string
    {
        $config = $this->settings->invoice();
        $padding = max(1, (int) ($config['number_padding'] ?? 4));
        $number = (int) ($config['next_number'] ?? 1001);
        $prefix = $config['prefix'] ?? 'INV';
        $code = $prefix.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

        Setting::saveSection('invoice', ['next_number' => $number + 1]);

        return $code;
    }

    public function create(array $data, bool $sendNotification = true): Invoice
    {
        $amount = (float) $data['amount'];
        $tax = $this->settings->calculateGst($amount);

        $invoice = Invoice::create([
            'invoice_code' => $this->nextInvoiceCode(),
            'student_id' => $data['student_id'],
            'payment_id' => $data['payment_id'] ?? null,
            'document_type' => $data['document_type'] ?? 'payment',
            'amount' => $amount,
            'gst_amount' => $tax['gst_amount'],
            'total' => $tax['total'],
            'status' => $data['status'] ?? 'pending',
            'issued_at' => now(),
        ]);

        $invoice->load('student');
        if ($sendNotification) {
            try {
                $this->notifications->invoiceGenerated($invoice);
            } catch (\Throwable) {
                // Invoice is saved; notification delivery is best-effort
            }
        }

        return $invoice;
    }

    public function createForPayment(Payment $payment, bool $sendNotification = true): Invoice
    {
        $existing = Invoice::where('payment_id', $payment->id)
            ->where('document_type', 'payment')
            ->first();
        if ($existing) {
            return $existing;
        }

        $payment->loadMissing('student');

        return $this->create([
            'student_id' => $payment->student_id,
            'payment_id' => $payment->id,
            'document_type' => 'payment',
            'amount' => (float) $payment->amount,
            'status' => $payment->status === 'paid' ? 'paid' : 'pending',
        ], $sendNotification);
    }

    public function createForRefund(Payment $payment): ?Invoice
    {
        $amount = (float) ($payment->refund_amount ?? 0);
        if ($amount <= 0 || ! $payment->student_id) {
            return null;
        }

        $existing = Invoice::where('payment_id', $payment->id)
            ->where('document_type', 'refund')
            ->first();
        if ($existing) {
            return $existing;
        }

        $payment->loadMissing('student');

        return $this->create([
            'student_id' => $payment->student_id,
            'payment_id' => $payment->id,
            'document_type' => 'refund',
            'amount' => $amount,
            'status' => $payment->refund_status === 'received' ? 'paid' : 'pending',
        ]);
    }

    public function markRefundInvoicePaid(Payment $payment): void
    {
        Invoice::where('payment_id', $payment->id)
            ->where('document_type', 'refund')
            ->where('status', 'pending')
            ->update(['status' => 'paid']);
    }

    public function maybeAutoGenerate(Payment $payment): ?Invoice
    {
        if (! $payment->student_id) {
            return null;
        }

        if (! ($this->settings->invoice()['auto_generate_on_payment'] ?? true)) {
            return null;
        }

        if (! in_array($payment->status, ['paid', 'pending'], true)) {
            return null;
        }

        return $this->createForPayment($payment);
    }

    public function recalculateAmounts(float $amount): array
    {
        return $this->settings->calculateGst($amount);
    }
}
