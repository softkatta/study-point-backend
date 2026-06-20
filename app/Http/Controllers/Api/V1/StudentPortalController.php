<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLogResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\AttendanceService;
use App\Services\PaymentGatewayService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\MediaUrl;
use App\Support\StudentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentPortalController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private PaymentGatewayService $gateway,
        private AttendanceService $attendance,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->subscriptions->syncExpiryStatuses();

        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $student->load(['branch', 'subscriptions' => fn ($q) => $q->latest()]);
        $subscription = $student->subscriptions->first();

        if (! $subscription) {
            $this->subscriptions->syncStudentMembership($student);
            $student = $student->fresh(['branch']);
            $student->setAttribute('plan_name', null);
            $student->setAttribute('valid_from', null);
            $student->setAttribute('expiry', null);
        }

        return ApiResponse::success([
            'student' => new StudentResource($student),
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            'days_remaining' => $student->expiry ? max(0, now()->diffInDays($student->expiry, false)) : 0,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $payments = $student->payments()->latest()->limit(50)->get();

        return ApiResponse::success(PaymentResource::collection($payments->load('student')));
    }

    public function invoices(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $invoices = $student->invoices()->latest()->limit(50)->get();

        return ApiResponse::success(InvoiceResource::collection($invoices->load('student')));
    }

    public function attendance(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $logs = $student->attendanceLogs()->latest('check_in')->limit(60)->get();

        return ApiResponse::success(AttendanceLogResource::collection($logs));
    }

    public function scanBranchQr(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $data = $request->validate([
            'qr' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $this->attendance->scanBranchQr($data['qr'], $request->user());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $log = $result['log'];

        return ApiResponse::success([
            'action' => $result['action'],
            'message' => $result['message'],
            'status' => $log->status,
            'time' => $result['time'],
            'check_in' => $log->check_in?->toIso8601String(),
            'check_out' => $log->check_out?->toIso8601String(),
            'branch' => $student->branch?->name,
            'course' => $student->plan_name,
        ], $result['message'], 201);
    }

    public function renew(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'payment_mode' => ['nullable', 'in:upi,card,netbanking,branch'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $subscription = $student->subscriptions()->latest()->first();

        if (! $subscription) {
            return ApiResponse::error('No subscription found.', 404);
        }

        $result = $this->subscriptions->requestRenewal($subscription, $plan);
        $payment = $result['payment'];
        $paymentMode = $data['payment_mode'] ?? 'branch';
        $checkout = null;
        $gateway = $this->gateway->checkoutConfig();

        if ($this->gateway->isOnlineMethod($paymentMode)) {
            $method = match ($paymentMode) {
                'upi' => 'UPI',
                'card' => 'Card',
                'netbanking' => 'Net Banking',
                default => 'UPI',
            };
            $payment->update(['method' => $method]);

            if ($this->gateway->isEnabled()) {
                try {
                    $checkout = $this->gateway->createOrder((float) $payment->amount, $payment->payment_code);
                } catch (\RuntimeException $e) {
                    return ApiResponse::error($e->getMessage(), 422);
                }
            }
        }

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => new PaymentResource($payment->fresh()),
            'checkout' => $checkout,
            'gateway' => $gateway,
        ], $checkout
            ? 'Renewal ready — complete online payment to activate'
            : 'Renewal request submitted — visit branch to pay or pay online');
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $student = StudentScope::require($request->user());
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $path = $request->file('photo')->store("students/{$student->id}", 'public');
        $student->update(['photo_path' => $path]);

        $fresh = $student->fresh(['branch']);

        return ApiResponse::success([
            'student' => new StudentResource($fresh),
            'photo_url' => MediaUrl::absolute(Storage::disk('public')->url($path)),
        ], 'Profile photo updated');
    }
}
