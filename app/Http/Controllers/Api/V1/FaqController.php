<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Faq::query()->orderBy('sort_order')->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success(
            FaqResource::collection($query->paginate($request->integer('per_page', 100)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $faq = Faq::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success(new FaqResource($faq), 'FAQ created', 201);
    }

    public function show(Faq $faq): JsonResponse
    {
        return ApiResponse::success(new FaqResource($faq));
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $faq->update($this->validated($request));

        return ApiResponse::success(new FaqResource($faq->fresh()), 'FAQ updated');
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return ApiResponse::success(null, 'FAQ deleted');
    }

    public function activate(Faq $faq): JsonResponse
    {
        $faq->update(['status' => 'active']);

        return ApiResponse::success(new FaqResource($faq->fresh()), 'FAQ activated');
    }

    public function deactivate(Faq $faq): JsonResponse
    {
        $faq->update(['status' => 'inactive']);

        return ApiResponse::success(new FaqResource($faq->fresh()), 'FAQ deactivated');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }
}
