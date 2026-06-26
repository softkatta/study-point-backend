<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppSettingsService;
use App\Services\WhatsAppMessageLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function metaVerify(Request $request, AppSettingsService $settings): Response|JsonResponse
    {
        $config = $settings->whatsapp();
        $verifyToken = (string) ($config['meta_webhook_verify_token'] ?? '');

        if (
            $request->query('hub_mode') === 'subscribe'
            && $verifyToken !== ''
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function meta(Request $request, AppSettingsService $settings, WhatsAppMessageLogService $log): Response|JsonResponse
    {
        if ($request->isMethod('GET')) {
            return $this->metaVerify($request, $settings);
        }

        $payload = $request->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    $externalId = (string) ($status['id'] ?? '');
                    if ($externalId === '') {
                        continue;
                    }

                    match ($status['status'] ?? '') {
                        'delivered' => $log->markDelivered($externalId),
                        'read' => $log->markRead($externalId),
                        'failed' => $this->markFailed($log, $externalId, $status),
                        default => null,
                    };
                }
            }
        }

        return response('OK', 200);
    }

    /** @param  array<string, mixed>  $status */
    private function markFailed(WhatsAppMessageLogService $log, string $externalId, array $status): void
    {
        $message = $log->findByExternalId($externalId);
        if (! $message) {
            return;
        }

        $errors = $status['errors'] ?? [];
        $detail = is_array($errors[0] ?? null)
            ? (string) ($errors[0]['title'] ?? $errors[0]['message'] ?? 'Delivery failed')
            : 'Delivery failed';

        $log->markFailed($message, $detail);
    }
}
