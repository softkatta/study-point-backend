<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Facility::query()->orderBy('sort_order')->orderBy('title');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success(
            FacilityResource::collection($query->paginate($request->integer('per_page', 100)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $facility = Facility::create($data);

        return ApiResponse::success(new FacilityResource($facility), 'Facility created', 201);
    }

    public function show(Facility $facility): JsonResponse
    {
        return ApiResponse::success(new FacilityResource($facility));
    }

    public function update(Request $request, Facility $facility): JsonResponse
    {
        $data = $this->validated($request, $facility);

        $facility->update($data);

        return ApiResponse::success(new FacilityResource($facility->fresh()), 'Facility updated');
    }

    public function destroy(Facility $facility): JsonResponse
    {
        $facility->delete();

        return ApiResponse::success(null, 'Facility deleted');
    }

    public function activate(Facility $facility): JsonResponse
    {
        $facility->update(['status' => 'active']);

        return ApiResponse::success(new FacilityResource($facility->fresh()), 'Facility activated');
    }

    public function deactivate(Facility $facility): JsonResponse
    {
        $facility->update(['status' => 'inactive']);

        return ApiResponse::success(new FacilityResource($facility->fresh()), 'Facility deactivated');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Facility $facility = null): array
    {
        $data = $request->validate([
            'slug' => [
                'nullable', 'string', 'max:60', 'alpha_dash',
                Rule::unique('facilities', 'slug')->ignore($facility?->id),
            ],
            'title' => [$facility ? 'sometimes' : 'required', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bullet_points' => ['nullable', 'array'],
            'bullet_points.*' => ['string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'show_in_nav' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'show_on_page' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $facility?->id);
        }

        if (isset($data['bullet_points'])) {
            $data['bullet_points'] = array_values(array_filter($data['bullet_points'], fn ($item) => filled($item)));
        }

        return $data;
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base ?: 'facility';
        $counter = 1;

        while (
            Facility::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
