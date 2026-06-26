<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\AttendanceLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class WhatsAppDispatchService
{
    public function __construct(
        private WhatsAppMessageLogService $log,
        private AppSettingsService $settings,
        private WhatsAppSenderService $whatsapp,
    ) {}

    public function queueText(string $phone, string $body, ?Model $related = null): ?WhatsAppMessage
    {
        if (! $this->canSend()) {
            return null;
        }

        $message = $this->log->createQueued(
            phone: $phone,
            messageType: 'text',
            body: $body,
            related: $related,
        );

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    public function queueTemplate(
        string $phone,
        string $templateName,
        array $bodyParams = [],
        string $language = 'en',
        ?Model $related = null,
    ): ?WhatsAppMessage {
        if (! $this->canSend()) {
            return null;
        }

        $templatePayload = [
            'name' => $templateName,
            'language' => $language,
            'body' => $bodyParams,
        ];

        if (! array_is_list($bodyParams)) {
            $templatePayload['body_keys'] = array_values(array_keys($bodyParams));
        }

        $message = $this->log->createQueued(
            phone: $phone,
            messageType: 'template',
            body: $templateName,
            templateParams: $templatePayload,
            related: $related,
        );

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    /**
     * @param  array{content: string, filename: string, mime?: string}|null  $pdf
     */
    public function queueDocument(
        string $phone,
        array $pdf,
        string $caption = '',
        ?Model $related = null,
    ): ?WhatsAppMessage {
        if (! $this->canSend()) {
            return null;
        }

        $path = $this->storeOutboxPdf($pdf['content'], $pdf['filename']);

        $message = $this->log->createQueued(
            phone: $phone,
            messageType: 'document',
            body: $caption,
            documentFilename: $pdf['filename'],
            documentPath: $path,
            related: $related,
        );

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    public function queuePaymentConfirmation(
        Payment $payment,
        string $fallbackText,
        ?array $pdf = null,
    ): void {
        $payment->loadMissing(['student.admission', 'admission']);
        $phone = $payment->student?->phone ?? $payment->admission?->phone;
        if (! $phone) {
            return;
        }

        $config = $this->settings->whatsapp();
        $template = trim((string) ($config['template_payment_success'] ?? ''));

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            $this->queueTemplate(
                $phone,
                $template,
                [
                    (string) ($payment->student?->name ?? $payment->admission?->name ?? 'Member'),
                    (string) $payment->payment_code,
                    '₹'.(string) $payment->amount,
                ],
                'en',
                $payment,
            );
        } else {
            $this->queueText($phone, $fallbackText, $payment);
        }

        if ($pdf) {
            $caption = "StudyPoint invoice for payment {$payment->payment_code}";
            $this->queueDocument($phone, $pdf, $caption, $payment);
        }
    }

    public function queuePaymentReceipt(
        Payment $payment,
        ?Invoice $invoice,
        InvoicePdfService $invoicePdf,
    ): void {
        $wa = $this->settings->whatsapp();
        if (! ($wa['notify_payment'] ?? true)) {
            return;
        }

        $payment->loadMissing(['student.admission', 'admission']);
        $phone = $payment->student?->phone ?? $payment->admission?->phone;
        if (! $phone) {
            return;
        }

        $message = "StudyPoint: Payment of ₹{$payment->amount} received. Receipt: {$payment->payment_code}.";
        $pdf = null;

        if ($invoice) {
            try {
                $pdf = $invoicePdf->build($invoice);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->queuePaymentConfirmation($payment, $message, $pdf);
    }

    public function queueInvoice(Invoice $invoice, InvoicePdfService $invoicePdf): ?WhatsAppMessage
    {
        $invoice->loadMissing('student.admission');
        $student = $invoice->student;
        if (! $student?->phone) {
            throw new \RuntimeException('Student phone number not found.');
        }

        if (! $this->canSend()) {
            throw new \RuntimeException('WhatsApp is not configured.');
        }

        $config = $this->settings->whatsapp();
        $template = trim((string) ($config['template_invoice'] ?? ''));

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            $this->queueTemplate(
                $student->phone,
                $template,
                [
                    (string) $student->name,
                    (string) $invoice->invoice_code,
                    '₹'.(string) $invoice->total,
                ],
                'en',
                $invoice,
            );
        }

        $pdf = $invoicePdf->build($invoice);
        $caption = "StudyPoint invoice {$invoice->invoice_code} · Total ₹{$invoice->total}";

        return $this->queueDocument($student->phone, $pdf, $caption, $invoice);
    }

    public function queueOtp(string $phone, string $code): ?WhatsAppMessage
    {
        $config = $this->settings->whatsapp();
        $template = trim((string) ($config['template_otp'] ?? ''));

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            return $this->queueTemplate($phone, $template, [$code]);
        }

        return $this->queueText($phone, "StudyPoint OTP: {$code}\n\nDo not share this code with anyone.");
    }

    public function queueRenewalReminder(Subscription $subscription, string $templateKey): ?WhatsAppMessage
    {
        $subscription->loadMissing('student');
        $student = $subscription->student;
        if (! $student?->phone) {
            return null;
        }

        $config = $this->settings->whatsapp();
        $toggleKey = match ($templateKey) {
            'template_renewal_7d' => 'notify_renewal_7d',
            'template_renewal_1d' => 'notify_renewal_1d',
            default => null,
        };

        if ($toggleKey && ! ($config[$toggleKey] ?? true)) {
            return null;
        }

        $template = trim((string) ($config[$templateKey] ?? ''));
        $expiry = $subscription->end_date?->format('d M Y') ?: 'soon';
        $fallback = "StudyPoint reminder: your membership expires on {$expiry}. Renew now to avoid interruption.";

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            return $this->queueTemplate(
                $student->phone,
                $template,
                [
                    (string) $student->name,
                    (string) ($subscription->plan_name ?? 'membership'),
                    $expiry,
                ],
                'en',
                $subscription,
            );
        }

        return $this->queueText($student->phone, $fallback, $subscription);
    }

    public function queueAttendanceAlert(Student $student, AttendanceLog $log): ?WhatsAppMessage
    {
        if (! $student->phone) {
            return null;
        }

        $config = $this->settings->whatsapp();
        if (! ($config['notify_attendance'] ?? true)) {
            return null;
        }

        $template = trim((string) ($config['template_attendance'] ?? ''));
        $action = $log->check_out ? 'checked out' : 'checked in';
        $timestamp = $log->check_in?->format('d M Y h:i A') ?? now()->format('d M Y h:i A');
        $fallback = "StudyPoint attendance update: You were {$action} at {$timestamp}.";

        if ($template !== '' && ($config['provider'] ?? '') === 'meta_cloud') {
            return $this->queueTemplate(
                $student->phone,
                $template,
                [
                    (string) $student->name,
                    $action,
                    $timestamp,
                ],
                'en',
                $log,
            );
        }

        return $this->queueText($student->phone, $fallback, $log);
    }

    private function canSend(): bool
    {
        return $this->whatsapp->isConfigured();
    }

    private function storeOutboxPdf(string $content, string $filename): string
    {
        $dir = storage_path('app/whatsapp/outbox');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/'.uniqid('wa_', true).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        file_put_contents($path, $content);

        return $path;
    }
}
