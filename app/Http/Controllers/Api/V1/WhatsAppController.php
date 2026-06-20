<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    private function readTemplates(): array
    {
        $raw = Setting::getSection('whatsapp')['templates'] ?? '[]';

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
    }

    private function saveTemplates(array $templates): void
    {
        Setting::saveSection('whatsapp', ['templates' => json_encode($templates)]);
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
        ]);

        $templates = $this->readTemplates();
        $template = ['id' => 'TPL-' . strtoupper(Str::random(6)), ...$data, 'status' => 'active'];
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

            return array_merge($t, $request->only(['name', 'body', 'category', 'status']));
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

    public function send(Request $request): JsonResponse
    {
        return ApiResponse::success(['queued' => true], 'Message queued');
    }

    public function sendBulk(Request $request): JsonResponse
    {
        return ApiResponse::success(['queued' => true, 'count' => $request->integer('count', 0)], 'Bulk message queued');
    }

    public function schedule(Request $request): JsonResponse
    {
        return ApiResponse::success(['scheduled' => true], 'Message scheduled');
    }

    public function deliveryStatus(string $id): JsonResponse
    {
        return ApiResponse::success(['id' => $id, 'status' => 'delivered']);
    }
}
