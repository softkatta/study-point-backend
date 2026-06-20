<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Testimonial::query()->orderBy('sort_order')->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success(
            TestimonialResource::collection($query->paginate($request->integer('per_page', 100)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $testimonial = Testimonial::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
            'rating' => $data['rating'] ?? 5,
        ]);

        return ApiResponse::success(new TestimonialResource($testimonial), 'Testimonial created', 201);
    }

    public function show(Testimonial $testimonial): JsonResponse
    {
        return ApiResponse::success(new TestimonialResource($testimonial));
    }

    public function update(Request $request, Testimonial $testimonial): JsonResponse
    {
        $testimonial->update($this->validated($request));

        return ApiResponse::success(new TestimonialResource($testimonial->fresh()), 'Testimonial updated');
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return ApiResponse::success(null, 'Testimonial deleted');
    }

    public function activate(Testimonial $testimonial): JsonResponse
    {
        $testimonial->update(['status' => 'active']);

        return ApiResponse::success(new TestimonialResource($testimonial->fresh()), 'Testimonial activated');
    }

    public function deactivate(Testimonial $testimonial): JsonResponse
    {
        $testimonial->update(['status' => 'inactive']);

        return ApiResponse::success(new TestimonialResource($testimonial->fresh()), 'Testimonial deactivated');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'quote' => ['required', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'avatar' => ['nullable', 'string', 'max:4'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }
}
