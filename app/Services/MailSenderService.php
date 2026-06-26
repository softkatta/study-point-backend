<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\MailDefaults;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MailSenderService
{
    public function __construct(
        private EmailTemplateService $templates,
    ) {}

    public function config(): array
    {
        return MailDefaults::merge(Setting::getSection('mail'));
    }

    public function isConfigured(): bool
    {
        $config = $this->config();
        $provider = $config['provider'] ?? 'smtp';

        return match ($provider) {
            'smtp' => ! empty($config['smtp_host'])
                && ! empty($config['smtp_username'])
                && ! empty($config['smtp_password']),
            'brevo' => ! empty($config['brevo_api_key']),
            'resend' => ! empty($config['resend_api_key']),
            'microsoft_graph' => ! empty($config['ms_tenant_id'])
                && ! empty($config['ms_client_id'])
                && ! empty($config['ms_client_secret'])
                && ! empty($config['ms_from_user']),
            'gmail_api' => ! empty($config['gmail_client_id'])
                && ! empty($config['gmail_refresh_token'])
                && ! empty($config['gmail_user_email']),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<int, array{content: string, filename?: string, name?: string, mime?: string}>  $attachments
     */
    public function send(string $to, string $subject, string $body, ?string $html = null, array $template = [], array $attachments = []): void
    {
        if ($html === null) {
            $html = $this->templates->render(array_merge([
                'title' => $subject,
                'paragraphs' => $this->templates->paragraphsFromText($body),
                'preheader' => $this->templates->excerpt($body),
                'details' => [],
            ], $template));
        }

        $config = $this->config();
        $provider = $config['provider'] ?? 'smtp';

        match ($provider) {
            'smtp' => $this->sendViaSmtp($config, $to, $subject, $body, $html, $attachments),
            'brevo' => $this->sendViaBrevo($config, $to, $subject, $body, $html, $attachments),
            'resend' => $this->sendViaResend($config, $to, $subject, $body, $html, $attachments),
            'microsoft_graph' => $this->sendViaMicrosoftGraph($config, $to, $subject, $body, $html, $attachments),
            'gmail_api' => $this->sendViaGmailApi($config, $to, $subject, $body, $html, $attachments),
            default => throw new RuntimeException("Unsupported mail provider: {$provider}"),
        };

        \Illuminate\Support\Facades\Log::info('StudyPoint email sent', [
            'to' => $to,
            'subject' => $subject,
            'provider' => $provider,
            'from' => $this->fromAddress($config)['email'],
        ]);
    }

    public function sendTest(string $to): void
    {
        $portalUrl = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/').'/login';

        $this->send(
            $to,
            'StudyPoint — Test Email',
            "This is a test email from StudyPoint. Your mail provider is configured correctly.\n\nIf you received this message, outbound email delivery is working as expected.",
            null,
            [
                'title' => 'Welcome to our family!',
                'eyebrow' => 'Test Email',
                'paragraphs' => [
                    'This is a test email from StudyPoint. Your mail provider is configured correctly.',
                    'If you received this message, outbound email delivery is working as expected.',
                ],
                'cta_label' => 'Visit portal',
                'cta_url' => $portalUrl,
            ],
        );
    }

    private function fromAddress(array $config): array
    {
        $username = trim((string) ($config['smtp_username'] ?? ''));
        $fromEmail = trim((string) ($config['from_email'] ?: 'noreply@studypoint.in'));

        // SMTP providers (Gmail, Hostinger, etc.) reject mail when From ≠ authenticated mailbox.
        if (($config['provider'] ?? 'smtp') === 'smtp' && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $username;
        }

        return [
            'email' => $fromEmail,
            'name' => $config['from_name'] ?: 'StudyPoint',
        ];
    }

    private function sendViaSmtp(array $config, string $to, string $subject, string $body, ?string $html, array $attachments = []): void
    {
        if (empty($config['smtp_host'])) {
            throw new RuntimeException('SMTP host is required.');
        }

        if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
            throw new RuntimeException('SMTP username and app password are required in Admin → Settings → Email.');
        }

        $encryption = ($config['smtp_encryption'] ?? 'tls') === 'none' ? null : ($config['smtp_encryption'] ?? 'tls');

        config([
            'mail.mailers.study_point' => [
                'transport' => 'smtp',
                'host' => $config['smtp_host'],
                'port' => (int) ($config['smtp_port'] ?: 587),
                'encryption' => $encryption,
                'username' => $config['smtp_username'] ?? null,
                'password' => $config['smtp_password'] ?? null,
                'timeout' => 10,
            ],
        ]);

        $from = $this->fromAddress($config);

        $callback = function ($message) use ($to, $subject, $from, $config, $attachments) {
            $message->to($to)
                ->subject($subject)
                ->from($from['email'], $from['name']);

            if (! empty($config['reply_to'])) {
                $message->replyTo($config['reply_to']);
            }

            foreach ($attachments as $attachment) {
                $message->attachData(
                    $attachment['content'],
                    $attachment['filename'] ?? $attachment['name'] ?? 'attachment.pdf',
                    ['mime' => $attachment['mime'] ?? 'application/octet-stream'],
                );
            }
        };

        if ($html) {
            Mail::mailer('study_point')->html($html, $callback);
        } else {
            Mail::mailer('study_point')->raw($body, $callback);
        }
    }

    private function sendViaBrevo(array $config, string $to, string $subject, string $body, ?string $html, array $attachments = []): void
    {
        if (empty($config['brevo_api_key'])) {
            throw new RuntimeException('Brevo API key is required.');
        }

        $from = $this->fromAddress($config);
        $payload = [
            'sender' => ['name' => $from['name'], 'email' => $from['email']],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'textContent' => $body,
        ];

        if ($html) {
            $payload['htmlContent'] = $html;
        }

        if (! empty($config['reply_to'])) {
            $payload['replyTo'] = ['email' => $config['reply_to']];
        }

        if ($attachments !== []) {
            $payload['attachment'] = array_map(fn (array $attachment) => [
                'content' => base64_encode($attachment['content']),
                'name' => $attachment['filename'] ?? $attachment['name'] ?? 'attachment.pdf',
            ], $attachments);
        }

        $response = Http::withHeaders([
            'api-key' => $config['brevo_api_key'],
            'accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Brevo API error: '.$this->httpError($response));
        }
    }

    private function sendViaResend(array $config, string $to, string $subject, string $body, ?string $html, array $attachments = []): void
    {
        if (empty($config['resend_api_key'])) {
            throw new RuntimeException('Resend API key is required.');
        }

        $from = $this->fromAddress($config);
        $payload = [
            'from' => "{$from['name']} <{$from['email']}>",
            'to' => [$to],
            'subject' => $subject,
            'text' => $body,
        ];

        if ($html) {
            $payload['html'] = $html;
        }

        if (! empty($config['reply_to'])) {
            $payload['reply_to'] = $config['reply_to'];
        }

        if ($attachments !== []) {
            $payload['attachments'] = array_map(fn (array $attachment) => [
                'filename' => $attachment['filename'] ?? $attachment['name'] ?? 'attachment.pdf',
                'content' => base64_encode($attachment['content']),
            ], $attachments);
        }

        $response = Http::withToken($config['resend_api_key'])
            ->post('https://api.resend.com/emails', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Resend API error: '.$this->httpError($response));
        }
    }

    private function sendViaMicrosoftGraph(array $config, string $to, string $subject, string $body, ?string $html, array $attachments = []): void
    {
        foreach (['ms_tenant_id', 'ms_client_id', 'ms_client_secret', 'ms_from_user'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Microsoft Graph requires tenant ID, client ID, client secret and sender mailbox.');
            }
        }

        $tokenResponse = Http::asForm()->post(
            "https://login.microsoftonline.com/{$config['ms_tenant_id']}/oauth2/v2.0/token",
            [
                'client_id' => $config['ms_client_id'],
                'client_secret' => $config['ms_client_secret'],
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Microsoft token error: '.$this->httpError($tokenResponse));
        }

        $accessToken = $tokenResponse->json('access_token');
        $contentType = $html ? 'HTML' : 'Text';

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => $contentType,
                'content' => $html ?: $body,
            ],
            'toRecipients' => [
                ['emailAddress' => ['address' => $to]],
            ],
            'from' => [
                'emailAddress' => [
                    'address' => $this->fromAddress($config)['email'],
                    'name' => $this->fromAddress($config)['name'],
                ],
            ],
        ];

        if ($attachments !== []) {
            $message['attachments'] = array_map(fn (array $attachment) => [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['filename'] ?? $attachment['name'] ?? 'attachment.pdf',
                'contentType' => $attachment['mime'] ?? 'application/pdf',
                'contentBytes' => base64_encode($attachment['content']),
            ], $attachments);
        }

        $response = Http::withToken($accessToken)->post(
            "https://graph.microsoft.com/v1.0/users/{$config['ms_from_user']}/sendMail",
            [
                'message' => $message,
                'saveToSentItems' => true,
            ]
        );

        if (! $response->successful() && $response->status() !== 202) {
            throw new RuntimeException('Microsoft Graph error: '.$this->httpError($response));
        }
    }

    private function sendViaGmailApi(array $config, string $to, string $subject, string $body, ?string $html, array $attachments = []): void
    {
        foreach (['gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token'] as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException('Gmail API requires client ID, client secret and refresh token.');
            }
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $config['gmail_client_id'],
            'client_secret' => $config['gmail_client_secret'],
            'refresh_token' => $config['gmail_refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Gmail token error: '.$this->httpError($tokenResponse));
        }

        $accessToken = $tokenResponse->json('access_token');
        $fromEmail = $config['gmail_user_email'] ?: $this->fromAddress($config)['email'];
        $fromName = $this->fromAddress($config)['name'];

        $mime = $this->buildMimeMessage($fromName, $fromEmail, $to, $subject, $body, $html, $attachments);

        $encoded = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $encoded]);

        if (! $response->successful()) {
            throw new RuntimeException('Gmail API error: '.$this->httpError($response));
        }
    }

    private function buildMimeMessage(
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $body,
        ?string $html,
        array $attachments = [],
    ): string {
        $mime = "From: {$fromName} <{$fromEmail}>\r\n";
        $mime .= "To: {$to}\r\n";
        $mime .= "Subject: {$subject}\r\n";
        $mime .= "MIME-Version: 1.0\r\n";

        $mixedBoundary = 'mixed_'.bin2hex(random_bytes(8));
        $altBoundary = 'alt_'.bin2hex(random_bytes(8));

        if ($attachments === []) {
            if ($html) {
                $mime .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
                $mime .= "--{$altBoundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n\r\n";
                $mime .= "--{$altBoundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n";
                $mime .= "--{$altBoundary}--";
            } else {
                $mime .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$body}";
            }

            return $mime;
        }

        $mime .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n\r\n";
        $mime .= "--{$mixedBoundary}\r\n";
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $mime .= "--{$altBoundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n\r\n";
        if ($html) {
            $mime .= "--{$altBoundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n";
        }
        $mime .= "--{$altBoundary}--\r\n";

        foreach ($attachments as $attachment) {
            $filename = $attachment['filename'] ?? $attachment['name'] ?? 'attachment.pdf';
            $mimeType = $attachment['mime'] ?? 'application/octet-stream';
            $mime .= "--{$mixedBoundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$filename}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $mime .= chunk_split(base64_encode($attachment['content']));
        }

        $mime .= "--{$mixedBoundary}--";

        return $mime;
    }

    private function httpError($response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            return (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
