<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Branch::withCount('students')->with('managers')->orderByDesc('is_head_office')->orderBy('name');

        if ($branchId = BranchScope::branchId($request->user())) {
            $query->where('id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success(
            BranchResource::collection($query->paginate($request->integer('per_page', 50)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:50'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'manager_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'operating_hours' => ['nullable', 'string', 'max:100'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:50'],
            'is_accepting_admissions' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
        ]);

        $branch = Branch::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'capacity' => $data['capacity'] ?? 100,
            'is_accepting_admissions' => $data['is_accepting_admissions'] ?? true,
            'attendance_qr_token' => \App\Services\AttendanceService::generateBranchAttendanceToken(),
        ]);

        return ApiResponse::success(new BranchResource($branch->loadCount('students')->load('managers')), 'Branch created', 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $branch);

        return ApiResponse::success(new BranchResource($branch->loadCount('students')->load('managers')));
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $branch);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch->id)],
            'name' => ['sometimes', 'string', 'max:100'],
            'city' => ['sometimes', 'string', 'max:50'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'manager_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'operating_hours' => ['nullable', 'string', 'max:100'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:50'],
            'is_accepting_admissions' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'maintenance'])],
            'revenue' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $branch->update($data);

        return ApiResponse::success(new BranchResource($branch->fresh()->loadCount('students')->load('managers')), 'Branch updated');
    }

    public function destroy(Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $branch);

        if ($branch->is_head_office) {
            return ApiResponse::error('Head office branch cannot be deleted. Edit it under Head Office or Branches.', 422);
        }

        $branch->delete();

        return ApiResponse::success(null, 'Branch deleted');
    }

    public function activate(Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $branch);

        $branch->update(['status' => 'active']);

        return ApiResponse::success(new BranchResource($branch->fresh()->loadCount('students')), 'Branch activated');
    }

    public function deactivate(Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $branch);

        $branch->update(['status' => 'inactive']);

        return ApiResponse::success(new BranchResource($branch->fresh()->loadCount('students')), 'Branch deactivated');
    }

    public function transferStudents(Request $request, Branch $branch): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $branch);

        $data = $request->validate([
            'target_branch_id' => ['required', 'exists:branches,id'],
        ]);

        $count = Student::where('branch_id', $branch->id)
            ->update(['branch_id' => $data['target_branch_id']]);

        return ApiResponse::success(['transferred' => $count], "{$count} students transferred");
    }
}
