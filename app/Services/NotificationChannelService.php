<?php

namespace App\Services;

use App\Models\Admission;

class NotificationChannelService
{
    public function __construct(
        private MailSenderService $mail,
        private WhatsAppSenderService $whatsapp,
    ) {}

    public function publicAvailability(): array
    {
        return [
            'email' => $this->mail->isConfigured(),
            'whatsapp' => $this->whatsapp->isConfigured(),
        ];
    }

    public function channelsForAdmission(?Admission $admission): array
    {
        if (! $admission) {
            return ['email' => true, 'whatsapp' => true];
        }

        return [
            'email' => (bool) $admission->notify_email,
            'whatsapp' => (bool) $admission->notify_whatsapp,
        ];
    }
}
