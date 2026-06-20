<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->subscriptions->syncExpiryStatuses();

        $query = Subscription::with(['student', 'student.branch', 'branch'])
            ->withExists(['payments as has_collected_payment' => function ($query) {
                $query->whereIn('status', ['paid', 'refund_pending', 'refunded']);
            }])
            ->latest();
        BranchScope::apply($query, $request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success(
            SubscriptionResource::collection($query->paginate($request->integer('per_page', 15)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'plan_name' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['end_date'] = $data['start_date'];

        $subscription = $this->subscriptions->createManual($data);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription created — collect payment to apply membership dates',
            201,
        );
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return ApiResponse::success(new SubscriptionResource($subscription->load('student')));
    }

    public function activate(Subscription $subscription): JsonResponse
    {
        if ($subscription->status === SubscriptionStatus::Pending) {
            return ApiResponse::error(
                'Collect payment first. Membership dates extend automatically after payment is received.',
                422,
            );
        }

        $subscription->update(['status' => SubscriptionStatus::Active]);

        if ($subscription->student) {
            $this->subscriptions->syncStudentMembership($subscription->student);
        }

        return ApiResponse::success(new SubscriptionResource($subscription->fresh(['student'])), 'Subscription activated');
    }

    public function pause(Subscription $subscription): JsonResponse
    {
        $subscription->update(['status' => SubscriptionStatus::Paused]);

        if ($subscription->student) {
            $this->subscriptions->syncStudentMembership($subscription->student);
        }

        return ApiResponse::success(new SubscriptionResource($subscription->fresh(['student'])), 'Subscription paused');
    }

    public function resume(Subscription $subscription): JsonResponse
    {
        $subscription->update(['status' => SubscriptionStatus::Active]);

        if ($subscription->student) {
            $this->subscriptions->syncStudentMembership($subscription->student);
        }

        return ApiResponse::success(new SubscriptionResource($subscription->fresh(['student'])), 'Subscription resumed');
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $result = $this->subscriptions->cancelSubscription($subscription);

        $parts = ['Subscription cancelled'];
        if ($result['pending_cancelled'] > 0) {
            $parts[] = $result['pending_cancelled'].' pending payment(s) cancelled';
        }
        if ($result['refund_amount'] > 0) {
            $parts[] = '₹'.number_format($result['refund_amount'], 2).' prorated refund pending (remaining days)';
        } else {
            $parts[] = 'no refund due';
        }

        return ApiResponse::success(
            new SubscriptionResource($result['subscription']),
            implode(' — ', $parts),
        );
    }

    public function renew(Subscription $subscription): JsonResponse
    {
        $result = $this->subscriptions->requestRenewal($subscription);

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => new \App\Http\Resources\PaymentResource($result['payment']),
        ], 'Renewal requested — collect payment to extend membership dates');
    }

    public function extend(Request $request, Subscription $subscription): JsonResponse
    {
        $days = $request->integer('days', 30);
        $end = ($subscription->end_date ?? now())->copy()->addDays($days);

        $subscription->update([
            'end_date' => $end->toDateString(),
        ]);

        $subscription = $this->subscriptions->refreshExpiryStatus($subscription->fresh('plan'));

        if ($subscription->student) {
            $this->subscriptions->syncStudentMembership($subscription->student);
        }

        return ApiResponse::success(new SubscriptionResource($subscription->fresh('student')), 'Validity extended');
    }

    public function upgrade(Request $request, Subscription $subscription): JsonResponse
    {
        return $this->changePlan($request, $subscription, 'upgrade');
    }

    public function downgrade(Request $request, Subscription $subscription): JsonResponse
    {
        return $this->changePlan($request, $subscription, 'downgrade');
    }

    private function changePlan(Request $request, Subscription $subscription, string $action): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'plan' => ['nullable', 'string'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        try {
            $result = $this->subscriptions->requestPlanChange($subscription, $plan, $action);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $message = $result['payment']
            ? 'Plan change pending — collect payment to apply the new plan (remaining days will be added to the new plan period)'
            : 'Plan changed — remaining days added to the new plan period';

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => $result['payment'] ? new \App\Http\Resources\PaymentResource($result['payment']) : null,
        ], $message);
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        if (! $this->subscriptions->canDeleteSubscription($subscription)) {
            return ApiResponse::error(
                'Cannot delete a subscription after payment has been collected. Cancel the subscription instead.',
                422,
            );
        }

        try {
            $this->subscriptions->deleteSubscription($subscription);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Subscription deleted');
    }
}
