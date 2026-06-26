<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageLogService;
use App\Services\WhatsAppSenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppSenderService $whatsapp, WhatsAppMessageLogService $log): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (! $message || $message->status === 'sent') {
            return;
        }

        if (! $whatsapp->isConfigured()) {
            $log->markFailed($message, 'WhatsApp provider is not configured.');

            return;
        }

        try {
            $result = match ($message->message_type) {
                'template' => $this->sendTemplate($whatsapp, $message),
                'document' => $this->sendDocument($whatsapp, $message),
                default => $whatsapp->send((string) $message->to_phone, (string) $message->body),
            };

            $log->markSent(
                $message,
                (string) ($result['external_id'] ?? ''),
                (string) ($result['provider'] ?? 'unknown'),
            );
        } catch (\Throwable $e) {
            $log->markFailed($message, $e->getMessage());
            throw $e;
        } finally {
            $this->cleanupDocument($message);
        }
    }

    /**
     * @return array{external_id: ?string, provider: string}
     */
    private function sendTemplate(WhatsAppSenderService $whatsapp, WhatsAppMessage $message): array
    {
        $params = $message->template_params ?? [];
        $templateName = (string) ($params['name'] ?? $message->body);
        $language = (string) ($params['language'] ?? 'en');
        $bodyParams = is_array($params['body'] ?? null) ? $params['body'] : [];
        $bodyKeys = is_array($params['body_keys'] ?? null) ? $params['body_keys'] : [];

        if ($bodyParams !== [] && $bodyKeys !== []) {
            $orderedParams = [];
            foreach ($bodyKeys as $key) {
                $orderedParams[] = isset($bodyParams[$key]) ? (string) $bodyParams[$key] : '';
            }
            $bodyParams = $orderedParams;
        } elseif (is_array($bodyParams)) {
            $bodyParams = array_values(array_map('strval', $bodyParams));
        }

        return $whatsapp->sendTemplate(
            (string) $message->to_phone,
            $templateName,
            $language,
            $bodyParams,
        );
    }

    /**
     * @return array{external_id: ?string, provider: string}
     */
    private function sendDocument(WhatsAppSenderService $whatsapp, WhatsAppMessage $message): array
    {
        $path = $message->document_path;
        if (! $path || ! is_file($path)) {
            throw new \RuntimeException('WhatsApp document file is missing.');
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            throw new \RuntimeException('Unable to read WhatsApp document file.');
        }

        return $whatsapp->sendDocument(
            (string) $message->to_phone,
            (string) ($message->document_filename ?: 'document.pdf'),
            $binary,
            (string) ($message->body ?? ''),
        );
    }

    private function cleanupDocument(WhatsAppMessage $message): void
    {
        $path = $message->document_path;
        if (! $path) {
            return;
        }

        if (str_starts_with($path, storage_path('app/whatsapp/outbox'))) {
            @unlink($path);
        }

        if (str_starts_with($path, 'whatsapp/outbox/')) {
            Storage::disk('local')->delete($path);
        }
    }
}
