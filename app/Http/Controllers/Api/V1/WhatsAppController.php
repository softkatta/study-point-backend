<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppDispatchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppDispatchService $dispatch) {}

    private function readTemplates(): array
    {
        $raw = \App\Models\Setting::getSection('whatsapp')['templates'] ?? '[]';

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
    }

    private function saveTemplates(array $templates): void
    {
        \App\Models\Setting::saveSection('whatsapp', ['templates' => json_encode($templates)]);
    }

    public function templates(): JsonResponse
    {
        return ApiResponse::success($this->readTemplates());
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'body' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'meta_template_name' => ['nullable', 'string'],
            'variables' => ['nullable', 'string'],
        ]);

        $templates = $this->readTemplates();
        $template = ['id' => 'TPL-'.strtoupper(Str::random(6)), ...$data, 'status' => 'active'];
        $templates[] = $template;
        $this->saveTemplates($templates);

        return ApiResponse::success($template, 'Template created', 201);
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        $templates = collect($this->readTemplates())->map(function ($t) use ($request, $id) {
            if (($t['id'] ?? '') !== $id) {
                return $t;
            }

            return array_merge($t, $request->only(['name', 'body', 'category', 'status', 'meta_template_name', 'variables']));
        })->all();

        $this->saveTemplates($templates);

        return ApiResponse::success($templates, 'Template updated');
    }

    public function deleteTemplate(string $id): JsonResponse
    {
        $templates = array_values(array_filter($this->readTemplates(), fn ($t) => ($t['id'] ?? '') !== $id));
        $this->saveTemplates($templates);

        return ApiResponse::success(null, 'Template deleted');
    }

    public function messages(Request $request): JsonResponse
    {
        $query = WhatsAppMessage::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('phone')) {
            $digits = preg_replace('/\D+/', '', (string) $request->input('phone')) ?: '';
            if ($digits !== '') {
                $tail = strlen($digits) > 10 ? substr($digits, -10) : $digits;
                $query->where(function ($q) use ($digits, $tail) {
                    $q->where('to_phone', 'like', '%'.$digits.'%')
                        ->orWhere('to_phone', 'like', '%'.$tail.'%');
                });
            }
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 25)));
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $message = $this->dispatch->queueText($data['phone'], $data['message']);
        if (! $message) {
            return ApiResponse::error('WhatsApp is not configured.', 422);
        }

        return ApiResponse::success([
            'queued' => true,
            'id' => $message->id,
            'status' => $message->status,
        ], 'Message queued');
    }

    public function sendBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phones' => ['required', 'array', 'min:1'],
            'phones.*' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $queued = [];
        foreach ($data['phones'] as $phone) {
            $message = $this->dispatch->queueText($phone, $data['message']);
            if ($message) {
                $queued[] = $message->id;
            }
        }

        return ApiResponse::success([
            'queued' => true,
            'count' => count($queued),
            'ids' => $queued,
        ], 'Bulk messages queued');
    }

    public function schedule(Request $request): JsonResponse
    {
        return ApiResponse::error('Scheduled WhatsApp campaigns are not enabled yet. Send immediately instead.', 501);
    }

    public function deliveryStatus(string $id): JsonResponse
    {
        $message = WhatsAppMessage::query()->find($id);
        if (! $message) {
            return ApiResponse::error('Message not found.', 404);
        }

        return ApiResponse::success([
            'id' => (string) $message->id,
            'status' => $message->status,
            'external_id' => $message->external_id,
            'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at,
            'read_at' => $message->read_at,
            'error_message' => $message->error_message,
        ]);
    }
}
