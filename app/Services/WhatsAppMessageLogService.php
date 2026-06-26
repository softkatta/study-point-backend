<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageLogService
{
    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function createQueued(
        string $phone,
        string $messageType,
        ?string $body = null,
        array $templateParams = [],
        ?string $documentFilename = null,
        ?string $documentPath = null,
        ?Model $related = null,
    ): WhatsAppMessage {
        return WhatsAppMessage::create([
            'to_phone' => $phone,
            'message_type' => $messageType,
            'body' => $body,
            'template_params' => $templateParams ?: null,
            'document_filename' => $documentFilename,
            'document_path' => $documentPath,
            'status' => 'queued',
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
        ]);
    }

    public function markSent(WhatsAppMessage $message, string $externalId, string $provider): WhatsAppMessage
    {
        $message->update([
            'status' => 'sent',
            'external_id' => $externalId,
            'provider' => $provider,
            'sent_at' => now(),
            'error_message' => null,
        ]);

        return $message->fresh();
    }

    public function markFailed(WhatsAppMessage $message, string $error): WhatsAppMessage
    {
        $message->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);

        return $message->fresh();
    }

    public function markDelivered(string $externalId): void
    {
        WhatsAppMessage::query()
            ->where('external_id', $externalId)
            ->whereNull('delivered_at')
            ->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
    }

    public function markRead(string $externalId): void
    {
        WhatsAppMessage::query()
            ->where('external_id', $externalId)
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    public function findByExternalId(string $externalId): ?WhatsAppMessage
    {
        return WhatsAppMessage::query()->where('external_id', $externalId)->first();
    }
}
