<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AttendanceLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Support\AdmissionPaymentLink;

class NotificationDispatchService
{
    public function __construct(
        private AppSettingsService $settings,
        private MailSenderService $mailer,
        private WhatsAppSenderService $whatsapp,
        private WhatsAppDispatchService $whatsappDispatch,
        private NotificationChannelService $channels,
        private InvoicePdfService $invoicePdf,
    ) {}

    /**
     * Stage 1 — public registration without payment: welcome + admission received.
     */
    public function admissionReceivedWelcome(Admission $admission): void
    {
        $prefs = $this->channels->channelsForAdmission($admission);
        if ((! $prefs['email'] && ! $prefs['whatsapp']) || (! $admission->email && ! $admission->phone)) {
            return;
        }

        $message = "Welcome to StudyPoint!\n\n"
            ."Your admission {$admission->admission_code} has been received.\n\n"
            ."Name: {$admission->name}\n"
            ."Plan: {$admission->plan_name}\n"
            ."Amount: ₹{$admission->amount}\n\n"
            .'Complete payment to activate your membership. Portal login details will be emailed after payment confirmation.';

        $paymentUrl = $this->admissionPaymentUrl($admission);

        $this->dispatchToContact(
            $admission->email,
            $admission->phone,
            $prefs,
            'Welcome to StudyPoint — Admission Received',
            $message,
            "StudyPoint: Admission {$admission->admission_code} received. Pay now: {$paymentUrl}",
            [
                'title' => 'Welcome to our family!',
                'eyebrow' => 'Admission',
                'paragraphs' => [
                    'Welcome to StudyPoint! We are delighted that you chose us for your study library membership.',
                    'Your admission request has been received and saved in our system. Click the button below to complete your payment — we will email your payment receipt, GST invoice, and portal login details once payment is confirmed.',
                ],
                'details' => [
                    ['label' => 'Admission code', 'value' => $admission->admission_code],
                    ['label' => 'Name', 'value' => $admission->name],
                    ['label' => 'Plan', 'value' => $admission->plan_name ?? '-'],
                    ['label' => 'Amount', 'value' => '₹'.$admission->amount],
                ],
                'cta_label' => 'Make payment',
                'cta_url' => $paymentUrl,
            ],
        );

        if ($prefs['email']
            && $admission->emergency_email
            && $admission->emergency_email !== $admission->email
            && $this->parentEmailNotificationsEnabled()
            && $admission->notify_parent_email
        ) {
            $this->dispatchEmailToContact(
                $admission->emergency_email,
                'Welcome to StudyPoint — Admission Received',
                $message,
                [
                    'title' => 'Welcome to our family!',
                    'eyebrow' => 'Admission',
                    'paragraphs' => [
                        'Welcome to StudyPoint! We are delighted that you chose us for your study library membership.',
                        'Your admission request has been received and saved in our system. Click the button below to complete your payment — we will email your payment receipt, GST invoice, and portal login details once payment is confirmed.',
                    ],
                    'details' => [
                        ['label' => 'Admission code', 'value' => $admission->admission_code],
                        ['label' => 'Name', 'value' => $admission->name],
                        ['label' => 'Plan', 'value' => $admission->plan_name ?? '-'],
                        ['label' => 'Amount', 'value' => '₹'.$admission->amount],
                    ],
                    'cta_label' => 'Make payment',
                    'cta_url' => $paymentUrl,
                ],
            );
        }
    }

    /**
     * Stage 2 — after payment: payment receipt + portal credentials in one email.
     */
    public function studentActivationNotice(
        Student $student,
        Admission $admission,
        Payment $payment,
        string $temporaryPassword,
    ): void {
        $student->loadMissing('admission');
        $admissionPrefs = $this->channels->channelsForAdmission($admission);
        $email = $student->email ?: $admission->email;
        $phone = $student->phone ?: $admission->phone;

        if (! $email) {
            throw new \RuntimeException('Student email is required to send activation notification.');
        }

        $this->assertMailConfigured();

        $paragraphs = [
            'Great news — your payment has been received and your StudyPoint membership is now active.',
            'Your payment receipt details and student portal login credentials are below. Your GST invoice is attached to this email. Please sign in and change your temporary password after your first login.',
        ];

        $details = [
            ['label' => 'Receipt no.', 'value' => $payment->payment_code],
            ['label' => 'Amount paid', 'value' => '₹'.$payment->amount],
            ['label' => 'Payment method', 'value' => ucfirst((string) ($payment->method ?? 'payment'))],
            [
                'label' => 'Payment date',
                'value' => optional($payment->paid_at ?? $payment->created_at)->format('d M Y') ?? '-',
            ],
            ['label' => 'Member ID', 'value' => $student->student_code],
            ['label' => 'Login email', 'value' => $email],
            ['label' => 'Temporary password', 'value' => $temporaryPassword],
        ];

        $message = implode("\n", [
            'StudyPoint — Payment confirmed & portal access',
            '',
            "Receipt: {$payment->payment_code}",
            "Amount paid: ₹{$payment->amount}",
            '',
            "Member ID: {$student->student_code}",
            "Login email: {$email}",
            "Temporary password: {$temporaryPassword}",
        ]);

        $waMessage = "StudyPoint payment confirmed!\n"
            ."Receipt: {$payment->payment_code} · ₹{$payment->amount}\n"
            ."Member ID: {$student->student_code}\n"
            ."Email: {$email}\n"
            ."Password: {$temporaryPassword}";

        $attachments = $this->invoiceAttachmentsForPayment($payment);

        $this->sendEmailToStudentAndEmergency(
            $email,
            $admission->emergency_email,
            'StudyPoint — Payment Confirmed & Portal Access',
            $message,
            [
                'title' => 'Membership activated!',
                'eyebrow' => 'Payment & Portal',
                'paragraphs' => $paragraphs,
                'details' => $details,
                'cta_label' => 'Sign in to portal',
                'cta_url' => $this->frontendUrl('/login'),
            ],
            $attachments,
            $this->parentEmailNotificationsEnabled() && $admission->notify_parent_email,
        );

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

        if ($prefs['email']) {
            $studentEmail = $student?->email ?? $admission?->email;
            $this->sendEmailToStudentAndEmergency(
                $studentEmail,
                $admission?->emergency_email,
                'StudyPoint — Payment Receipt',
                $message,
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
                [],
                $this->parentEmailNotificationsEnabled() && ($admission?->notify_parent_email ?? false),
            );
        }

        if ($prefs['whatsapp']) {
            try {
                $invoice = $payment->student_id
                    ? \App\Models\Invoice::query()->where('payment_id', $payment->id)->latest('id')->first()
                    : null;
                $pdf = null;
                if ($invoice) {
                    try {
                        $pdf = $this->invoicePdf->build($invoice);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
                $this->whatsappDispatch->queuePaymentConfirmation($payment, $message, $pdf);
            } catch (\Throwable $e) {
                report($e);
            }
        }
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
            try {
                $this->whatsappDispatch->queueInvoice($invoice, $this->invoicePdf);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function sendInvoiceEmail(Invoice $invoice): void
    {
        $invoice->loadMissing('student.admission');
        $student = $invoice->student;
        if (! $student?->email) {
            throw new \RuntimeException('Student email not found.');
        }

        $this->assertMailConfigured();

        $pdf = $this->invoicePdf->build($invoice);
        $message = "StudyPoint: Please find your invoice {$invoice->invoice_code} attached as a PDF.\n\nTotal: ₹{$invoice->total}.";

        $this->sendEmailToStudentAndEmergency(
            $student->email,
            $student->admission?->emergency_email,
            'Invoice '.$invoice->invoice_code.' — StudyPoint',
            $message,
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
            $this->parentEmailNotificationsEnabled() && ($student->admission?->notify_parent_email ?? false),
        );
    }

    public function sendInvoiceWhatsApp(Invoice $invoice): void
    {
        $invoice->loadMissing('student.admission');
        $student = $invoice->student;

        if (! $student?->phone) {
            throw new \RuntimeException('Student phone number not found.');
        }

        if (! $this->whatsapp->isConfigured()) {
            throw new \RuntimeException('WhatsApp is not configured. Go to Admin → Settings → WhatsApp and complete setup.');
        }

        $message = "StudyPoint invoice {$invoice->invoice_code}\n"
            ."Amount: ₹{$invoice->amount}\n"
            ."GST: ₹{$invoice->gst_amount}\n"
            ."Total: ₹{$invoice->total}\n"
            .'View in portal: '.$this->frontendUrl('/student/invoices');

        $this->whatsappDispatch->queueInvoice($invoice, $this->invoicePdf);
    }

    public function portalWelcome(Student $student, string $temporaryPassword): void
    {
        $student->loadMissing('admission');
        $admission = $this->resolveAdmissionForStudent($student);
        $payment = $this->resolvePaidPaymentForStudent($student, $admission);

        if (! $payment) {
            throw new \RuntimeException('A paid payment record is required to send activation notification.');
        }

        $this->studentActivationNotice($student, $admission, $payment, $temporaryPassword);
    }

    private function admissionPaymentUrl(Admission $admission): string
    {
        return AdmissionPaymentLink::frontendUrl($admission->id);
    }

    /**
     * @return array<int, array{content: string, filename?: string, name?: string, mime?: string}>
     */
    private function invoiceAttachmentsForPayment(Payment $payment): array
    {
        if ($payment->status !== 'paid' || ! $payment->student_id) {
            return [];
        }

        try {
            $invoice = app(InvoiceService::class)->createForPayment($payment->fresh(['student']), sendNotification: false);
            $pdf = $this->invoicePdf->build($invoice);

            return [$pdf];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function assertMailConfigured(): void
    {
        if (! $this->mailer->isConfigured()) {
            throw new \RuntimeException('Email is not configured. Go to Admin → Settings → Email and complete SMTP setup.');
        }
    }

    private function resolveAdmissionForStudent(Student $student): Admission
    {
        if ($student->admission) {
            return $student->admission;
        }

        $latestPayment = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->latest('id')
            ->first();

        return new Admission([
            'admission_code' => $student->student_code,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'plan_name' => $student->plan_name ?? '-',
            'amount' => $latestPayment?->amount ?? 0,
            'notify_email' => true,
            'notify_whatsapp' => false,
        ]);
    }

    private function resolvePaidPaymentForStudent(Student $student, Admission $admission): ?Payment
    {
        if ($admission->exists) {
            $payment = Payment::query()
                ->where('admission_id', $admission->id)
                ->where('status', 'paid')
                ->latest('id')
                ->first();

            if ($payment) {
                return $payment;
            }
        }

        return Payment::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->latest('id')
            ->first();
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
                $this->whatsappDispatch->queueText($phone, $whatsappBody);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $emailTemplate
     */
    private function dispatchEmailToContact(
        ?string $email,
        string $emailSubject,
        string $emailBody,
        array $emailTemplate = [],
        array $attachments = [],
    ): void {
        if (! $email) {
            return;
        }

        try {
            $this->mailer->send($email, $emailSubject, $emailBody, null, $emailTemplate, $attachments);
        } catch (\Throwable) {
            // non-blocking
        }
    }

    /**
     * @param  array<int, array{content: string, filename?: string, name?: string, mime?: string}>  $attachments
     */
    private function sendEmailToStudentAndEmergency(
        ?string $studentEmail,
        ?string $emergencyEmail,
        string $subject,
        string $body,
        array $template = [],
        array $attachments = [],
        bool $sendEmergency = true,
    ): void {
        if ($studentEmail) {
            $this->dispatchEmailToContact($studentEmail, $subject, $body, $template, $attachments);
        }

        if ($sendEmergency && $emergencyEmail && $emergencyEmail !== $studentEmail) {
            $this->dispatchEmailToContact($emergencyEmail, $subject, $body, $template, $attachments);
        }
    }

    private function parentEmailNotificationsEnabled(): bool
    {
        return (bool) ($this->settings->mail()['notify_parent_email'] ?? true);
    }

    public function attendanceAlert(Student $student, AttendanceLog $log): void
    {
        if (! $this->mailer->isConfigured()) {
            return;
        }

        $student->loadMissing('admission');
        $admission = $student->admission;
        $action = $log->check_out ? 'checked out' : 'checked in';
        $timestamp = $log->check_in?->format('d M Y h:i A') ?? now()->format('d M Y h:i A');
        $subject = "StudyPoint attendance update — {$student->name} {$action}";
        $body = implode("\n", [
            'Hello,',
            '',
            "Attendance has been recorded for {$student->name}.",
            '',
            "Status: {$action}",
            "Time: {$timestamp}",
        ]);

        $template = [
            'title' => 'Attendance update',
            'eyebrow' => 'Attendance',
            'paragraphs' => [
                "Attendance has been recorded for {$student->name}.",
                "This student was {$action} at {$timestamp}.",
            ],
            'details' => [
                ['label' => 'Student', 'value' => $student->name],
                ['label' => 'Action', 'value' => ucfirst($action)],
                ['label' => 'Time', 'value' => $timestamp],
            ],
            'cta_label' => 'View portal',
            'cta_url' => $this->frontendUrl('/login'),
        ];

        $this->sendEmailToStudentAndEmergency(
            $student->email,
            $admission?->emergency_email,
            $subject,
            $body,
            $template,
            [],
            $this->parentEmailNotificationsEnabled() && ($admission?->notify_parent_email ?? false),
        );
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
