<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Support\ApiResponse;
use App\Support\PlanCategoryDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function catalog(): JsonResponse
    {
        $plans = Plan::orderBy('price')->get();

        return ApiResponse::success(PlanResource::collection($plans));
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50', Rule::in(PlanCategoryDefaults::categories())],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
            'badge' => ['nullable', 'string', 'max:80'],
            'is_featured' => ['sometimes', 'boolean'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['string', 'max:120'],
        ]);

        $plan->update(PlanCategoryDefaults::apply($data));

        return ApiResponse::success(new PlanResource($plan->fresh()), 'Plan updated');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'unique:plans,slug'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50', Rule::in(PlanCategoryDefaults::categories())],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'description' => ['nullable', 'string'],
            'badge' => ['nullable', 'string', 'max:80'],
            'is_featured' => ['sometimes', 'boolean'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['string', 'max:120'],
        ]);

        $data = PlanCategoryDefaults::apply($data);

        $plan = Plan::create([
            ...$data,
            'duration_months' => $data['duration_months'] ?? 1,
            'duration_days' => $data['duration_days'] ?? 30,
            'status' => 'active',
        ]);

        return ApiResponse::success(new PlanResource($plan), 'Plan created', 201);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete();

        return ApiResponse::success(null, 'Plan deleted');
    }

    public function activate(Plan $plan): JsonResponse
    {
        $plan->update(['status' => 'active']);

        return ApiResponse::success(new PlanResource($plan->fresh()), 'Plan activated');
    }

    public function deactivate(Plan $plan): JsonResponse
    {
        $plan->update(['status' => 'inactive']);

        return ApiResponse::success(new PlanResource($plan->fresh()), 'Plan deactivated');
    }

    public function duplicate(Plan $plan): JsonResponse
    {
        $copy = $plan->replicate(['slug']);
        $copy->slug = $plan->slug . '-copy-' . time();
        $copy->name = $plan->name . ' (Copy)';
        $copy->save();

        return ApiResponse::success(new PlanResource($copy), 'Plan duplicated', 201);
    }
}
