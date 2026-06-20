<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\AppSettingsService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoices,
        private AppSettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['student.branch', 'student.admission'])->latest('issued_at');

        if ($branchId = BranchScope::branchId($request->user())) {
            $query->whereIn('student_id', function ($q) use ($branchId) {
                $q->select('id')->from('students')->where('branch_id', $branchId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_code', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($sq) => $sq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('student_code', 'like', "%{$search}%"));
            });
        }

        return ApiResponse::success(
            InvoiceResource::collection($query->paginate($request->integer('per_page', 50)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['paid', 'pending', 'cancelled'])],
        ]);

        $student = \App\Models\Student::findOrFail($data['student_id']);
        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $student->branch_id !== $branchId) {
                return ApiResponse::error('Student does not belong to your branch.', 403);
            }
        }

        $invoice = $this->invoices->create([
            'student_id' => $student->id,
            'payment_id' => $data['payment_id'] ?? null,
            'amount' => (float) $data['amount'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return ApiResponse::success(
            new InvoiceResource($invoice->load('student')),
            'Invoice generated',
            201,
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing(['student.branch', 'student.admission', 'payment']);

        return ApiResponse::success([
            'invoice' => new InvoiceResource($invoice),
            'document' => $this->settings->invoiceDocumentMeta(),
        ]);
    }

    public function pdf(Invoice $invoice, InvoicePdfService $pdf)
    {
        $built = $pdf->build($invoice);

        return response($built['content'], 200, [
            'Content-Type' => $built['mime'],
            'Content-Disposition' => 'attachment; filename="'.$built['filename'].'"',
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['paid', 'pending', 'cancelled'])],
        ]);

        if (isset($data['amount'])) {
            $tax = $this->invoices->recalculateAmounts((float) $data['amount']);
            $data['gst_amount'] = $tax['gst_amount'];
            $data['total'] = $tax['total'];
        }

        $invoice->update($data);

        return ApiResponse::success(new InvoiceResource($invoice->fresh('student')), 'Invoice updated');
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        $invoice->update(['status' => 'cancelled']);

        return ApiResponse::success(new InvoiceResource($invoice->fresh('student')), 'Invoice cancelled');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($branchId = BranchScope::branchId(request()->user())) {
            $invoice->loadMissing('student');
            if (! $invoice->student || (int) $invoice->student->branch_id !== $branchId) {
                return ApiResponse::error('Invoice does not belong to your branch.', 403);
            }
        }

        $invoice->delete();

        return ApiResponse::success(null, 'Invoice deleted');
    }

    public function fromPayment(\App\Models\Payment $payment): JsonResponse
    {
        $invoice = $this->invoices->createForPayment($payment);

        return ApiResponse::success(
            new InvoiceResource($invoice->load('student')),
            'Invoice generated from payment',
            201,
        );
    }
}
