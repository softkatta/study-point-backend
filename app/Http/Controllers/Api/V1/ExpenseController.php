<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('branch')->latest('expense_date');

        BranchScope::apply($query, $request->user(), 'branch_id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success(
            ExpenseResource::collection($query->paginate($request->integer('per_page', 50)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'expense_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        if ($branchId = BranchScope::branchId($request->user())) {
            $data['branch_id'] = $branchId;
        }

        $expense = Expense::create([
            ...$data,
            'status' => $data['status'] ?? 'pending',
        ]);

        return ApiResponse::success(
            new ExpenseResource($expense->load('branch')),
            'Expense recorded',
            201,
        );
    }

    public function show(Expense $expense): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $expense);

        return ApiResponse::success(new ExpenseResource($expense->load('branch')));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'category' => ['sometimes', 'string', 'max:50'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'expense_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $expense->branch_id !== $branchId) {
                return ApiResponse::error('Expense does not belong to your branch.', 403);
            }
            unset($data['branch_id']);
        }

        $expense->update($data);

        return ApiResponse::success(new ExpenseResource($expense->fresh('branch')), 'Expense updated');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if ($branchId = BranchScope::branchId(request()->user())) {
            if ((int) $expense->branch_id !== $branchId) {
                return ApiResponse::error('Expense does not belong to your branch.', 403);
            }
        }

        if ($expense->bill_path) {
            Storage::disk('public')->delete($expense->bill_path);
        }

        $expense->delete();

        return ApiResponse::success(null, 'Expense deleted');
    }

    public function approve(Expense $expense): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $expense);

        $expense->update(['status' => 'approved']);

        return ApiResponse::success(new ExpenseResource($expense->fresh('branch')), 'Expense approved');
    }

    public function reject(Expense $expense): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $expense);

        $expense->update(['status' => 'rejected']);

        return ApiResponse::success(new ExpenseResource($expense->fresh('branch')), 'Expense rejected');
    }

    public function uploadBill(Request $request, Expense $expense): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $expense);

        $request->validate([
            'bill' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if ($expense->bill_path) {
            Storage::disk('public')->delete($expense->bill_path);
        }

        $path = $request->file('bill')->store("expenses/{$expense->id}", 'public');
        $expense->update(['bill_path' => $path]);

        return ApiResponse::success(new ExpenseResource($expense->fresh('branch')), 'Bill uploaded');
    }

    public function downloadBill(Expense $expense): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $expense);

        if (! $expense->bill_path || ! Storage::disk('public')->exists($expense->bill_path)) {
            return ApiResponse::error('Bill not found.', 404);
        }

        return ApiResponse::success([
            'url' => Storage::disk('public')->url($expense->bill_path),
            'path' => $expense->bill_path,
        ]);
    }
}
