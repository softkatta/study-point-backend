<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\StoreAdmissionRequest;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Admission;
use App\Models\Payment;
use App\Services\AdmissionDocumentService;
use App\Services\AdmissionService;
use App\Services\NotificationChannelService;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use App\Support\AdmissionPaymentLink;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function __construct(
        private AdmissionService $admissionService,
        private AdmissionDocumentService $documentService,
        private PaymentService $paymentService,
        private PaymentGatewayService $gateway,
        private NotificationChannelService $notificationChannels,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->admissionService->reconcileUnpaidActivations();

        $query = Admission::with(['branch', 'plan', 'student', 'subscription'])->latest();
        BranchScope::apply($query, $request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('admission_code', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success(
            AdmissionResource::collection($query->paginate($request->integer('per_page', 15)))
        );
    }

    public function store(StoreAdmissionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user && ($branchId = BranchScope::branchId($user))) {
            $data['branch_id'] = $branchId;
            $source = AdmissionSource::Branch;
        } elseif ($user && $request->input('source') === 'admin') {
            $source = AdmissionSource::Admin;
        } else {
            $source = AdmissionSource::from($request->input('source', 'online'));
        }

        if ($source === AdmissionSource::Online) {
            $available = $this->notificationChannels->publicAvailability();
            $notifyEmail = $request->boolean('notify_email');
            $notifyWhatsapp = $request->boolean('notify_whatsapp');

            if ($available['email'] || $available['whatsapp']) {
                if (! $notifyEmail && ! $notifyWhatsapp) {
                    return ApiResponse::error('Select at least one notification channel (Email or WhatsApp).', 422);
                }
                if ($notifyEmail && ! $available['email']) {
                    return ApiResponse::error('Email notifications are not configured.', 422);
                }
                if ($notifyWhatsapp && ! $available['whatsapp']) {
                    return ApiResponse::error('WhatsApp notifications are not configured.', 422);
                }
            }

            $data['notify_email'] = $notifyEmail && $available['email'];
            $data['notify_whatsapp'] = $notifyWhatsapp && $available['whatsapp'];
        }

        $admission = $this->admissionService->create($data, $source, $user?->id);

        $payload = (new AdmissionResource($admission->load(['branch', 'plan', 'payments'])))->resolve($request);

        return ApiResponse::success($payload, 'Admission submitted successfully', 201);
    }

    public function show(Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $admission);

        return ApiResponse::success(new AdmissionResource($admission->load(['branch', 'plan', 'documents'])));
    }

    public function update(Request $request, Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $admission);
        if ($request->hasAny(['follow_up_date', 'follow_up_note']) && ! $request->filled('first_name')) {
            $data = $request->validate([
                'follow_up_date' => ['nullable', 'date'],
                'follow_up_note' => ['nullable', 'string', 'max:500'],
            ]);
            $admission->update($data);

            return ApiResponse::success(new AdmissionResource($admission->fresh(['branch', 'plan'])), 'Follow-up saved');
        }

        if (! in_array($admission->status, [AdmissionStatus::Pending, AdmissionStatus::Verified], true)) {
            return ApiResponse::error('Only pending or verified admissions can be modified.', 422);
        }

        $data = $request->validate((new StoreAdmissionRequest)->rules());
        $admission->update($data);
        $this->admissionService->syncPaymentRecord($admission->fresh());

        return ApiResponse::success(new AdmissionResource($admission->fresh(['branch', 'plan'])), 'Admission updated');
    }

    public function destroy(Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $admission);

        $summary = $this->admissionService->deleteCascade($admission);

        return ApiResponse::success($summary, 'Admission and related records permanently deleted');
    }

    public function verify(Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $admission);

        try {
            $updated = $this->admissionService->verify($admission);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new AdmissionResource($updated), 'Documents verified');
    }

    public function approve(Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $admission);

        try {
            $result = $this->admissionService->approve($admission);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'admission' => new AdmissionResource($result['admission']),
            'student' => new StudentResource($result['student']->load('branch')),
            'subscription' => new SubscriptionResource($result['subscription']),
            'portal_ready' => (bool) $result['student']->user_id,
        ], 'Admission approved — student and subscription created');
    }

    public function reject(Request $request, Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $admission);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $updated = $this->admissionService->reject($admission, $request->reason);

        return ApiResponse::success(new AdmissionResource($updated), 'Admission rejected');
    }

    public function uploadDocuments(Request $request, Admission $admission): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $admission);

        if ($admission->status !== AdmissionStatus::Pending) {
            return ApiResponse::error('Documents can only be uploaded for pending admissions.', 422);
        }

        $request->validate([
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'id_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'address_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $updated = $this->documentService->store($admission, [
            'photo' => $request->file('photo'),
            'id_proof' => $request->file('id_proof'),
            'address_proof' => $request->file('address_proof'),
        ]);

        return ApiResponse::success(
            new AdmissionResource($updated),
            'Documents uploaded successfully',
        );
    }

    public function collectPayment(Request $request, Admission $admission): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:Cash,Check,cash,check,cheque'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
        ]);

        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $admission->branch_id !== $branchId) {
                return ApiResponse::error('Admission does not belong to your branch.', 403);
            }
        }

        try {
            $payment = $this->paymentService->collectForAdmission($admission, $data);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $payment = $payment->load(['student', 'admission', 'subscription']);
        $admission = $payment->admission?->fresh(['student', 'subscription', 'branch', 'plan']);
        $meta = $this->paymentService->activationMeta($payment);
        $message = $meta['credentials_sent'] && $meta['credentials_email']
            ? 'Payment collected — membership activated. Payment receipt and portal login details sent to '.$meta['credentials_email'].'.'
            : 'Payment collected — membership activated automatically';

        return ApiResponse::success([
            'payment' => new PaymentResource($payment),
            'admission' => $admission ? new AdmissionResource($admission) : null,
            'student' => $admission?->student ? new StudentResource($admission->student->load('branch')) : null,
            'subscription' => $admission?->subscription ? new SubscriptionResource($admission->subscription) : null,
            'auto_approved' => (bool) ($admission && $admission->status === AdmissionStatus::Active && $admission->student_id),
            'portal_ready' => (bool) $meta['portal_ready'],
            ...$meta,
        ], $message);
    }

    public function resumePayment(Request $request, Admission $admission): JsonResponse
    {
        $data = $request->validate([
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'max:128'],
        ]);

        if (! AdmissionPaymentLink::verify($admission->id, (int) $data['expires'], $data['signature'])) {
            return ApiResponse::error('This payment link is invalid or has expired.', 403);
        }

        $admission->loadMissing(['branch', 'plan']);
        $payAtBranch = $admission->payment_mode === 'branch';

        if ($admission->payment_status === 'paid') {
            return ApiResponse::success([
                'admission_id' => $admission->id,
                'admission_code' => $admission->admission_code,
                'name' => $admission->name,
                'payment_status' => 'paid',
                'pay_at_branch' => $payAtBranch,
                'can_pay_online' => false,
            ], 'Payment already completed');
        }

        return ApiResponse::success([
            'admission_id' => $admission->id,
            'admission_code' => $admission->admission_code,
            'name' => $admission->name,
            'email' => $admission->email,
            'phone' => $admission->phone,
            'amount' => (float) $admission->amount,
            'plan_name' => $admission->plan_name,
            'payment_mode' => $admission->payment_mode,
            'payment_status' => $admission->payment_status,
            'pay_at_branch' => $payAtBranch,
            'can_pay_online' => ! $payAtBranch && $this->gateway->isOnlineMethod((string) $admission->payment_mode),
            'branch_name' => $admission->branch?->name,
            'branch_city' => $admission->branch?->city,
        ]);
    }

    public function checkout(Admission $admission): JsonResponse
    {
        if ($admission->payment_status === 'paid') {
            return ApiResponse::error('Payment already completed for this admission.', 422);
        }

        $payment = Payment::where('admission_id', $admission->id)->first();
        if (! $payment) {
            $this->admissionService->syncPaymentRecord($admission);
            $payment = Payment::where('admission_id', $admission->id)->first();
        }

        if (! $payment || $payment->status === 'paid') {
            return ApiResponse::error('No pending payment found for this admission.', 422);
        }

        $gateway = $this->gateway->checkoutConfig();
        $checkout = null;

        if ($this->gateway->isEnabled() && $this->gateway->isOnlineMethod((string) $admission->payment_mode)) {
            try {
                $checkout = $this->gateway->createOrder((float) $payment->amount, $payment->payment_code);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        return ApiResponse::success([
            'admission' => new AdmissionResource($admission->load(['branch', 'plan'])),
            'payment' => new PaymentResource($payment),
            'checkout' => $checkout,
            'gateway' => $gateway,
        ]);
    }

    public function confirmPayment(Request $request, Admission $admission): JsonResponse
    {
        $payment = Payment::where('admission_id', $admission->id)->first();
        if (! $payment) {
            return ApiResponse::error('No payment found for this admission.', 404);
        }

        if ($payment->status === 'paid') {
            $admission = $payment->admission?->fresh(['student', 'subscription', 'branch', 'plan']);
            if ($admission && $admission->status !== AdmissionStatus::Active) {
                $this->admissionService->tryAutoApproveAfterPayment($admission);
                $admission = $admission->fresh(['student', 'subscription', 'branch', 'plan']);
            }

            return ApiResponse::success([
                'payment' => new PaymentResource($payment->load(['student', 'admission'])),
                'admission' => $admission ? new AdmissionResource($admission) : null,
                'student' => $admission?->student ? new StudentResource($admission->student->load('branch')) : null,
                'subscription' => $admission?->subscription ? new SubscriptionResource($admission->subscription) : null,
                'auto_approved' => (bool) ($admission && $admission->status === AdmissionStatus::Active && $admission->student_id),
                'portal_ready' => (bool) $admission?->student?->user_id,
            ], 'Payment already confirmed');
        }

        $data = $request->validate([
            'razorpay_order_id' => ['nullable', 'string', 'max:100'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:100'],
            'razorpay_signature' => ['nullable', 'string', 'max:255'],
            'demo' => ['nullable', 'boolean'],
        ]);

        $demo = (bool) ($data['demo'] ?? false);
        $gateway = $this->gateway->checkoutConfig();

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
        } elseif (! ($gateway['test_mode'] ?? true)) {
            return ApiResponse::error('Demo payment is only allowed in test mode.', 422);
        }

        $method = match ($admission->payment_mode) {
            'upi' => 'UPI',
            'card' => 'Card',
            'netbanking' => 'Net Banking',
            default => 'UPI',
        };

        $updated = $this->paymentService->markPaid($payment, [
            'method' => $method,
            'transaction_id' => $data['razorpay_payment_id'] ?? ('DEMO-'.$payment->payment_code),
        ]);

        $admission = $updated->admission?->fresh(['student', 'subscription', 'branch', 'plan']);
        $autoApproved = $admission && $admission->status === AdmissionStatus::Active && $admission->student_id;

        return ApiResponse::success([
            'payment' => new PaymentResource($updated->load(['student', 'admission'])),
            'admission' => $admission ? new AdmissionResource($admission) : null,
            'student' => $admission?->student ? new StudentResource($admission->student->load('branch')) : null,
            'subscription' => $admission?->subscription ? new SubscriptionResource($admission->subscription) : null,
            'auto_approved' => (bool) $autoApproved,
            'portal_ready' => (bool) $admission?->student?->user_id,
        ], $autoApproved ? 'Payment confirmed — admission approved and membership activated' : 'Payment confirmed');
    }
}
