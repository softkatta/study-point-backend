<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;

class NotificationDispatchService
{
    public function __construct(
        private AppSettingsService $settings,
        private MailSenderService $mailer,
        private WhatsAppSenderService $whatsapp,
        private NotificationChannelService $channels,
        private InvoicePdfService $invoicePdf,
    ) {}

    public function admissionSubmitted(Admission $admission): void
    {
        $prefs = $this->channels->channelsForAdmission($admission);
        $message = "StudyPoint: Your admission {$admission->admission_code} has been received.\n\n"
            ."Name: {$admission->name}\n"
            ."Plan: {$admission->plan_name}\n"
            ."Amount: ₹{$admission->amount}\n\n"
            .'Complete payment to activate your membership.';

        $this->dispatchToContact(
            $admission->email,
            $admission->phone,
            $prefs,
            'Admission Received — StudyPoint',
            $message,
            null,
            [
                'title' => 'Admission received!',
                'eyebrow' => 'Admission',
                'paragraphs' => [
                    'Thank you for choosing StudyPoint. We have received your admission request and it is now in our system.',
                    'Complete payment to activate your membership. Our team will review and approve your admission after payment confirmation.',
                ],
                'details' => [
                    ['label' => 'Admission code', 'value' => $admission->admission_code],
                    ['label' => 'Name', 'value' => $admission->name],
                    ['label' => 'Plan', 'value' => $admission->plan_name],
                    ['label' => 'Amount', 'value' => '₹'.$admission->amount],
                ],
                'cta_label' => 'Complete payment',
                'cta_url' => $this->frontendUrl('/admission'),
            ],
        );
    }

    public function paymentReceipt(Payment $payment): void
    {
        $wa = $this->settings->whatsapp();
        if (! ($wa['notify_payment'] ?? true)) {
            return;
        }

        $payment->loadMissing(['student.admission', 'admission']);
        $student = $payment->student;
        $admission = $payment->admission ?? $student?->admission;
        if (! $student && ! $admission) {
            return;
        }

        $prefs = $this->channels->channelsForAdmission($admission);
        $message = "StudyPoint: Payment of ₹{$payment->amount} received. Receipt: {$payment->payment_code}.";

        $this->dispatchToContact(
            $student?->email ?? $admission?->email,
            $student?->phone ?? $admission?->phone,
            $prefs,
            'StudyPoint — Payment Receipt',
            $message,
            null,
            [
                'title' => 'Payment received',
                'eyebrow' => 'Payment',
                'paragraphs' => [
                    'We have successfully received your payment. Thank you for your trust in StudyPoint.',
                    'Your receipt details are below for your records.',
                ],
                'details' => [
                    ['label' => 'Receipt no.', 'value' => $payment->payment_code],
                    ['label' => 'Amount', 'value' => '₹'.$payment->amount],
                    ['label' => 'Method', 'value' => ucfirst((string) ($payment->method ?? 'payment'))],
                    ['label' => 'Status', 'value' => ucfirst((string) ($payment->status ?? 'paid'))],
                ],
                'cta_label' => 'View portal',
                'cta_url' => $this->frontendUrl('/login'),
            ],
        );
    }

    public function invoiceGenerated(Invoice $invoice): void
    {
        $invoiceSettings = $this->settings->invoice();
        $invoice->loadMissing('student.admission');
        $student = $invoice->student;
        if (! $student) {
            return;
        }

        $prefs = $this->channels->channelsForAdmission($student->admission);
        $message = "StudyPoint: Invoice {$invoice->invoice_code} generated. Total: ₹{$invoice->total}. PDF attached.";

        if ($invoiceSettings['send_email_on_generate'] ?? true) {
            if ($prefs['email'] && $student->email) {
                try {
                    $this->sendInvoiceEmail($invoice);
                } catch (\Throwable) {
                    // non-blocking
                }
            }
        }

        if (($invoiceSettings['send_whatsapp_on_generate'] ?? false) && $prefs['whatsapp']) {
            $this->sendWhatsApp($student->phone, $message);
        }
    }

    public function sendInvoiceEmail(Invoice $invoice): void
    {
        $invoice->loadMissing('student.admission');
        $student = $invoice->student;
        if (! $student?->email) {
            throw new \RuntimeException('Student email not found.');
        }

        $pdf = $this->invoicePdf->build($invoice);
        $message = "StudyPoint: Please find your invoice {$invoice->invoice_code} attached as a PDF.\n\nTotal: ₹{$invoice->total}.";

        $this->mailer->send(
            $student->email,
            'Invoice '.$invoice->invoice_code.' — StudyPoint',
            $message,
            null,
            [
                'title' => 'Your invoice is ready',
                'eyebrow' => 'Invoice',
                'paragraphs' => [
                    'Your GST invoice is attached to this email as a PDF document.',
                    'You can also sign in to the student portal to view your billing history.',
                ],
                'details' => [
                    ['label' => 'Invoice no.', 'value' => $invoice->invoice_code],
                    ['label' => 'Total', 'value' => '₹'.$invoice->total],
                    ['label' => 'Date', 'value' => optional($invoice->issued_at ?? $invoice->created_at)->format('d M Y') ?? '-'],
                ],
                'cta_label' => 'View portal',
                'cta_url' => $this->frontendUrl('/login'),
            ],
            [$pdf],
        );
    }

    public function portalWelcome(Student $student, string $temporaryPassword): void
    {
        $student->loadMissing('admission');
        $prefs = $this->channels->channelsForAdmission($student->admission);

        $message = "Welcome to StudyPoint!\n\nMember ID: {$student->student_code}\nLogin email: {$student->email}\nTemporary password: {$temporaryPassword}\n\nPlease sign in and change your password.";
        $waMessage = "StudyPoint portal login:\nMember ID: {$student->student_code}\nEmail: {$student->email}\nPassword: {$temporaryPassword}\n\nLogin and change your password.";

        $this->dispatchToContact(
            $student->email,
            $student->phone,
            $prefs,
            'Welcome to StudyPoint — Portal Access',
            $message,
            $waMessage,
            [
                'title' => 'Welcome to our family!',
                'eyebrow' => 'Welcome',
                'paragraphs' => [
                    'Your StudyPoint student portal account is now active. Use the credentials below to sign in.',
                    'For your security, please change your temporary password immediately after your first login.',
                ],
                'details' => [
                    ['label' => 'Member ID', 'value' => $student->student_code],
                    ['label' => 'Login email', 'value' => $student->email],
                    ['label' => 'Temporary password', 'value' => $temporaryPassword],
                ],
                'cta_label' => 'Sign in to portal',
                'cta_url' => $this->frontendUrl('/login'),
            ],
        );
    }

    public function contactFormReceived(array $data): void
    {
        $general = $this->settings->general();
        $to = $general['support_email'] ?? null;

        if (! $to) {
            return;
        }

        try {
            $body = "New contact form message\n\nName: {$data['name']}\nEmail: {$data['email']}\nPhone: ".($data['phone'] ?? '-')."\n\n{$data['message']}";
            $this->mailer->send(
                $to,
                'StudyPoint Contact — '.$data['name'],
                $body,
                null,
                [
                    'title' => 'New contact message',
                    'eyebrow' => 'Contact',
                    'paragraphs' => [
                        'Someone submitted the contact form on your StudyPoint website.',
                        'Message details are listed below.',
                    ],
                    'details' => [
                        ['label' => 'Name', 'value' => $data['name']],
                        ['label' => 'Email', 'value' => $data['email']],
                        ['label' => 'Phone', 'value' => $data['phone'] ?? '-'],
                        ['label' => 'Message', 'value' => $data['message']],
                    ],
                    'cta_label' => 'Reply by email',
                    'cta_url' => 'mailto:'.$data['email'],
                ],
            );
        } catch (\Throwable) {
            // Contact form still succeeds; mail failure is non-blocking
        }
    }

    /**
     * @param  array<string, mixed>  $emailTemplate
     */
    private function dispatchToContact(
        ?string $email,
        ?string $phone,
        array $prefs,
        string $emailSubject,
        string $emailBody,
        ?string $whatsappBody = null,
        array $emailTemplate = [],
    ): void {
        $whatsappBody ??= $emailBody;

        if ($prefs['email'] && $email) {
            try {
                $this->mailer->send($email, $emailSubject, $emailBody, null, $emailTemplate);
            } catch (\Throwable) {
                // non-blocking
            }
        }

        if ($prefs['whatsapp'] && $phone) {
            try {
                $this->whatsapp->send($phone, $whatsappBody);
            } catch (\Throwable) {
                // non-blocking
            }
        }
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function sendEmail(?string $email, string $subject, string $message, array $template = []): void
    {
        if (! $email) {
            return;
        }

        $this->mailer->send($email, $subject, $message, null, $template);
    }

    private function sendWhatsApp(?string $phone, string $message): void
    {
        if (! $phone) {
            return;
        }

        $this->whatsapp->send($phone, $message);
    }

    private function frontendUrl(string $path = ''): string
    {
        $base = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/');

        if ($path === '') {
            return $base;
        }

        return $base.'/'.ltrim($path, '/');
    }
}
