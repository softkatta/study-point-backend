<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdmissionStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Admission;
use App\Models\Payment;
use App\Models\Student;
use App\Services\AdmissionService;
use App\Services\PaymentGatewayService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayService $gateway,
        private PaymentService $payments,
        private AdmissionService $admissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->admissions->reconcileUnpaidActivations();

        $query = Payment::with(['student', 'admission'])->latest();

        if ($branchId = BranchScope::branchId($request->user())) {
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('student', fn ($sq) => $sq->where('branch_id', $branchId))
                    ->orWhereHas('admission', fn ($aq) => $aq->where('branch_id', $branchId));
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
                $q->where('payment_code', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($sq) => $sq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('student_code', 'like', "%{$search}%"))
                    ->orWhereHas('admission', fn ($aq) => $aq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('admission_code', 'like', "%{$search}%"));
            });
        }

        return ApiResponse::success(
            PaymentResource::collection($query->paginate($request->integer('per_page', 50)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required_without:admission_id', 'nullable', 'exists:students,id'],
            'admission_id' => ['required_without:student_id', 'nullable', 'exists:admissions,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required_without:student_id', 'nullable', 'string', 'max:30'],
            'status' => ['nullable', 'in:paid,pending,failed,refunded,cancelled,refund_pending'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
        ]);

        if (! empty($data['admission_id'])) {
            return $this->collectForAdmission($request, Admission::findOrFail($data['admission_id']), $data);
        }

        $student = Student::findOrFail($data['student_id']);
        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $student->branch_id !== $branchId) {
                return ApiResponse::error('Student does not belong to your branch.', 403);
            }
        }

        $method = $data['method'] ?? 'Cash';
        $status = $data['status'] ?? 'pending';
        $amount = $data['amount'] ?? 0;

        if ($this->gateway->isOnlineMethod($method)) {
            if ($status === 'paid') {
                return ApiResponse::error('Online payments must be completed via the payment gateway.', 422);
            }

            try {
                $this->gateway->assertEnabled();
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
            $status = 'pending';
        } elseif ($status === 'paid') {
            try {
                $this->gateway->assertCounterCollection($method);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        $payment = Payment::create([
            'payment_code' => $this->nextCode(),
            'student_id' => $student->id,
            'subscription_id' => $data['subscription_id'] ?? null,
            'amount' => $amount,
            'method' => $method,
            'status' => $status,
            'transaction_id' => $data['transaction_id'] ?? null,
            'paid_at' => $status === 'paid' ? ($data['payment_date'] ?? now()) : null,
        ]);

        if ($status === 'paid') {
            $payment = $this->payments->markPaid($payment, [
                'method' => $method,
                'transaction_id' => $data['transaction_id'] ?? null,
                'paid_at' => $data['payment_date'] ?? now(),
            ]);
        }

        $checkout = null;
        if ($payment->status === 'pending' && $this->gateway->isOnlineMethod($method)) {
            try {
                $checkout = $this->gateway->createOrder((float) $payment->amount, $payment->payment_code);
            } catch (RuntimeException $e) {
                $payment->update(['status' => 'failed']);

                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        $payload = (new PaymentResource($payment->load(['student', 'admission'])))->resolve($request);
        if ($checkout) {
            $payload['checkout'] = $checkout;
        }
        $payload['gateway'] = $this->gateway->checkoutConfig();

        return ApiResponse::success($payload, 'Payment recorded', 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return ApiResponse::success(new PaymentResource($payment->load(['student', 'admission'])));
    }

    public function verify(Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return ApiResponse::success(new PaymentResource($payment->load(['student', 'admission'])), 'Payment already verified');
        }

        if ($this->gateway->isOnlineMethod((string) $payment->method)) {
            return ApiResponse::error('Online payments must be confirmed via the payment gateway, not manually.', 422);
        }

        $updated = $this->payments->markPaid($payment);

        return ApiResponse::success(new PaymentResource($updated->load(['student', 'admission'])), 'Payment verified');
    }

    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return ApiResponse::success(new PaymentResource($payment->load(['student', 'admission'])), 'Payment already confirmed');
        }

        $data = $request->validate([
            'razorpay_order_id' => ['nullable', 'string', 'max:100'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:100'],
            'razorpay_signature' => ['nullable', 'string', 'max:255'],
            'demo' => ['nullable', 'boolean'],
        ]);

        $demo = (bool) ($data['demo'] ?? false);
        $config = $this->gateway->checkoutConfig();

        if (! $demo) {
            if (empty($data['razorpay_order_id']) || empty($data['razorpay_payment_id']) || empty($data['razorpay_signature'])) {
                return ApiResponse::error('Payment confirmation details are required.', 422);
            }

            if (! $this->gateway->verifyRazorpaySignature(
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
                $data['razorpay_signature'],
            )) {
                return ApiResponse::error('Invalid payment signature.', 422);
            }
        } elseif (! ($config['test_mode'] ?? true)) {
            return ApiResponse::error('Demo payment is only allowed in test mode.', 422);
        }

        $updated = $this->payments->markPaid($payment, [
            'method' => $payment->method ?? 'UPI',
            'transaction_id' => $data['razorpay_payment_id'] ?? ('DEMO-'.$payment->payment_code),
        ]);

        return $this->paymentConfirmResponse($updated, $request);
    }

    public function collect(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return $this->paymentConfirmResponse($payment->load(['student', 'admission', 'subscription']), $request, 'Payment already collected');
        }

        $data = $request->validate([
            'method' => ['required', 'string', 'max:30'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
        ]);

        try {
            $updated = $this->payments->collectAtCounter($payment, $data);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return $this->paymentConfirmResponse($updated->load(['student', 'admission', 'subscription']), $request, 'Payment collected — membership activated automatically');
    }

    private function paymentConfirmResponse(Payment $payment, Request $request, string $message = 'Payment confirmed'): JsonResponse
    {
        $admission = $payment->admission?->fresh(['student', 'subscription', 'branch', 'plan']);
        $subscription = $payment->subscription?->fresh(['student']);
        $autoApproved = $admission && $admission->status === AdmissionStatus::Active && $admission->student_id;
        $renewalActivated = $subscription
            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Renewed, SubscriptionStatus::ExpiringSoon], true)
            && ! $payment->admission_id;

        return ApiResponse::success([
            'payment' => new PaymentResource($payment->load(['student', 'admission', 'subscription'])),
            'admission' => $admission ? new AdmissionResource($admission) : null,
            'student' => $admission?->student
                ? new StudentResource($admission->student->load('branch'))
                : ($payment->student ? new StudentResource($payment->student->load('branch')) : null),
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            'auto_approved' => (bool) $autoApproved,
            'renewal_activated' => (bool) $renewalActivated,
            'portal_ready' => (bool) ($admission?->student?->user_id ?? $payment->student?->user_id),
            ...$this->payments->activationMeta($payment),
        ], $renewalActivated
            ? 'Payment confirmed — subscription renewed and activated'
            : ($autoApproved ? 'Payment confirmed — admission approved and membership activated' : $message));
    }

    public function refund(Payment $payment): JsonResponse
    {
        try {
            if ($payment->refund_status === 'pending') {
                $payment = $this->payments->markRefundReceived($payment);
            } else {
                $refundAmount = (float) ($payment->refund_amount ?? $payment->amount);
                $payment->update([
                    'refund_amount' => $refundAmount,
                    'refund_status' => 'received',
                    'status' => 'refunded',
                    'refunded_at' => now(),
                ]);
                $payment = $payment->fresh(['student', 'admission', 'subscription']);
                app(InvoiceService::class)->createForRefund($payment);
                app(InvoiceService::class)->markRefundInvoicePaid($payment);
            }
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            new PaymentResource($payment),
            $payment->refund_status === 'received'
                ? 'Refund marked as received by student'
                : 'Payment refunded',
        );
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return ApiResponse::success(null, 'Payment deleted');
    }

    private function collectForAdmission(Request $request, Admission $admission, array $data): JsonResponse
    {
        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $admission->branch_id !== $branchId) {
                return ApiResponse::error('Admission does not belong to your branch.', 403);
            }
        }

        try {
            $payment = $this->payments->collectForAdmission($admission, [
                'method' => $data['method'] ?? 'Cash',
                'transaction_id' => $data['transaction_id'] ?? null,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            new PaymentResource($payment->load(['student', 'admission'])),
            'Payment collected',
            201,
        );
    }

    private function nextCode(): string
    {
        $year = date('Y');
        $last = Payment::withTrashed()
            ->where('payment_code', 'like', "PAY-{$year}-%")
            ->orderByDesc('id')
            ->value('payment_code');

        $num = $last ? (int) substr($last, -3) + 1 : 1;

        return 'PAY-' . $year . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }
}
