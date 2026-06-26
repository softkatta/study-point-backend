<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MailSenderService;
use App\Services\NotificationDispatchService;
use App\Services\WhatsAppSenderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function send(
        Request $request,
        NotificationDispatchService $dispatch,
        MailSenderService $mailer,
        WhatsAppSenderService $whatsapp,
        string $channel = '',
        string $resource = '',
        string $id = '',
    ): JsonResponse {
        $channel = $channel ?: $request->route('channel', 'email');
        $resource = $resource ?: $request->route('resource', 'notification');
        $id = $id ?: (string) ($request->route('payment')?->id ?? $request->route('invoice')?->id ?? '');

        if ($resource === 'invoices' && $invoice = $request->route('invoice')) {
            if ($channel === 'email') {
                try {
                    $dispatch->sendInvoiceEmail($invoice);

                    return ApiResponse::success([
                        'channel' => $channel,
                        'resource' => $resource,
                        'id' => $id,
                        'sent' => true,
                    ], 'Invoice email sent with PDF attachment');
                } catch (\Throwable $e) {
                    return ApiResponse::error('Failed to send invoice email: '.$e->getMessage(), 422);
                }
            }

            if ($channel === 'whatsapp') {
                try {
                    $message = app(\App\Services\WhatsAppDispatchService::class)->queueInvoice(
                        $invoice,
                        app(\App\Services\InvoicePdfService::class),
                    );

                    return ApiResponse::success([
                        'channel' => $channel,
                        'resource' => $resource,
                        'id' => $id,
                        'queued' => true,
                        'message_id' => $message?->id,
                    ], 'Invoice WhatsApp queued');
                } catch (\Throwable $e) {
                    return ApiResponse::error('Failed to queue WhatsApp: '.$e->getMessage(), 422);
                }
            }
        }

        if ($resource === 'payments' && $payment = $request->route('payment')) {
            if ($channel === 'email') {
                try {
                    $dispatch->paymentReceipt($payment);

                    return ApiResponse::success([
                        'channel' => $channel,
                        'resource' => $resource,
                        'id' => $id,
                        'sent' => true,
                    ], 'Payment receipt email sent');
                } catch (\Throwable $e) {
                    return ApiResponse::error('Failed to send payment email: '.$e->getMessage(), 422);
                }
            }

            if ($channel === 'whatsapp') {
                try {
                    $dispatch->paymentReceipt($payment);

                    return ApiResponse::success([
                        'channel' => $channel,
                        'resource' => $resource,
                        'id' => $id,
                        'queued' => true,
                    ], 'Payment receipt WhatsApp queued');
                } catch (\Throwable $e) {
                    return ApiResponse::error('Failed to queue WhatsApp: '.$e->getMessage(), 422);
                }
            }
        }

        $user = $request->user();
        $sent = false;

        if ($channel === 'email' && $user?->email) {
            try {
                $mailer->send(
                    $user->email,
                    'StudyPoint — '.ucfirst($resource).' notification',
                    "Your {$resource} notification (#{$id}) has been processed by StudyPoint.",
                    null,
                    [
                        'title' => ucfirst($resource).' notification',
                        'eyebrow' => 'Notification',
                        'paragraphs' => [
                            "Your {$resource} notification (#{$id}) has been processed by StudyPoint.",
                            'If you have any questions, please contact our support team.',
                        ],
                        'cta_label' => 'Visit portal',
                        'cta_url' => rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/').'/login',
                    ],
                );
                $sent = true;
            } catch (\Throwable $e) {
                return ApiResponse::error('Failed to send email: '.$e->getMessage(), 422);
            }
        }

        if ($channel === 'whatsapp' && $user?->phone) {
            try {
                $whatsapp->send(
                    $user->phone,
                    "StudyPoint: Your {$resource} notification (#{$id}) has been processed.",
                );
                $sent = true;
            } catch (\Throwable $e) {
                return ApiResponse::error('Failed to send WhatsApp: '.$e->getMessage(), 422);
            }
        }

        return ApiResponse::success([
            'channel' => $channel,
            'resource' => $resource,
            'id' => $id,
            'queued' => ! $sent,
            'sent' => $sent,
        ], ucfirst($channel).' notification sent');
    }
}
