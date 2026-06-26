<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Admission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use App\Support\PlanCategoryDefaults;
use App\Support\PlanDuration;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private BiometricAccessService $biometricAccess,
    ) {}

    public function createFromAdmission(Admission $admission, Student $student): Subscription
    {
        $admission->loadMissing('plan');
        $endDate = PlanDuration::endDateForAdmission($admission);
        $isPaid = $admission->payment_status === 'paid';

        return Subscription::create([
            'subscription_code' => $this->nextSubscriptionCode(),
            'student_id' => $student->id,
            'plan_id' => $admission->plan_id,
            'branch_id' => $admission->branch_id,
            'plan_name' => $admission->plan_name ?? 'Monthly Pass',
            'plan_category' => $admission->plan?->category ?? 'Monthly',
            'start_date' => $admission->start_date,
            'end_date' => $endDate,
            'status' => $isPaid ? SubscriptionStatus::Active : SubscriptionStatus::Pending,
            'membership_source' => 'new',
            'amount' => $admission->amount,
            'auto_renew' => false,
        ]);
    }

    public function activateMembership(?Student $student, ?Subscription $subscription = null): void
    {
        if ($student && $student->status === StudentStatus::Pending) {
            $student->update(['status' => StudentStatus::Active]);
        }

        if ($subscription && $subscription->status === SubscriptionStatus::Pending) {
            $subscription->update(['status' => SubscriptionStatus::Active]);

            return;
        }

        if ($student) {
            Subscription::where('student_id', $student->id)
                ->where('status', SubscriptionStatus::Pending)
                ->latest('id')
                ->first()
                ?->update(['status' => SubscriptionStatus::Active]);
        }
    }

    public function syncExpiryStatuses(): void
    {
        Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Renewed,
                SubscriptionStatus::ExpiringSoon,
            ])
            ->whereNotNull('end_date')
            ->with('plan')
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $status = $this->resolveExpiryStatus($subscription, $subscription->plan);
                    if ($subscription->status !== $status) {
                        $subscription->update(['status' => $status]);
                    }
                }
            });

        $today = now()->toDateString();

        Student::query()
            ->where('status', StudentStatus::Active)
            ->whereDate('expiry', '<', $today)
            ->lazyById()
            ->each(function (Student $student) {
                $student->update(['status' => StudentStatus::Expired]);
                $this->biometricAccess->syncStudentAccess($student->fresh());
            });
    }

    public function requestRenewal(Subscription $subscription, ?Plan $plan = null): array
    {
        $subscription->loadMissing('student');
        $plan ??= $subscription->plan_id ? Plan::find($subscription->plan_id) : null;
        $amount = $plan?->price ?? $subscription->amount;

        // Await payment before extending membership dates.
        $subscription->update([
            'plan_id' => $plan?->id ?? $subscription->plan_id,
            'plan_name' => $plan?->name ?? $subscription->plan_name,
            'plan_category' => $plan?->category ?? $subscription->plan_category,
            'amount' => $amount,
            'status' => SubscriptionStatus::Pending,
        ]);

        $payment = $this->createRenewalPayment($subscription->fresh(), $amount, 'Pending', 'renew');

        return [
            'subscription' => $subscription->fresh(['student']),
            'payment' => $payment,
        ];
    }

    public function requestPlanChange(Subscription $subscription, Plan $targetPlan, string $action): array
    {
        $subscription->loadMissing('student');

        if ($subscription->plan_id && $subscription->plan_id === $targetPlan->id) {
            throw new \InvalidArgumentException('Subscription is already on this plan.');
        }

        $currentAmount = (float) $subscription->amount;
        $targetAmount = (float) $targetPlan->price;

        if ($action === 'upgrade' && $targetAmount <= $currentAmount) {
            throw new \InvalidArgumentException('Select a higher-priced plan to upgrade.');
        }

        if ($action === 'downgrade' && $targetAmount >= $currentAmount) {
            throw new \InvalidArgumentException('Select a lower-priced plan to downgrade.');
        }

        $chargeAmount = $action === 'upgrade' ? max(0, $targetAmount - $currentAmount) : 0;

        if ($chargeAmount <= 0) {
            return [
                'subscription' => $this->applyPlanChangeImmediately($subscription, $targetPlan),
                'payment' => null,
            ];
        }

        // Keep current plan and end date until payment is collected.
        $subscription->update([
            'status' => SubscriptionStatus::Pending,
        ]);

        $payment = $this->createRenewalPayment(
            $subscription->fresh(),
            $chargeAmount,
            'Pending',
            $action,
            $targetPlan->id,
        );

        return [
            'subscription' => $subscription->fresh(['student']),
            'payment' => $payment,
        ];
    }

    public function completeRenewalOnPayment(Payment $payment): void
    {
        if (! $payment->subscription_id) {
            return;
        }

        $subscription = Subscription::find($payment->subscription_id);
        if (! $subscription) {
            return;
        }

        $action = $this->normalizeSubscriptionAction($subscription, $payment->subscription_action);

        if (in_array($action, ['upgrade', 'downgrade'], true)) {
            $this->completePlanChangeOnPayment($subscription, $payment);

            return;
        }

        $plan = $subscription->plan_id ? Plan::find($subscription->plan_id) : null;
        $period = $this->resolvePeriodOnPayment($subscription, $plan, $action);
        $membershipSource = $action === 'renew' ? 'renew' : 'new';

        $subscription->update([
            'start_date' => $period['start'],
            'end_date' => $period['end'],
            'membership_source' => $membershipSource,
        ]);

        $subscription = $subscription->fresh(['plan']);
        $subscription->update([
            'status' => $this->resolveExpiryStatus($subscription, $plan),
        ]);

        if ($subscription->student) {
            $this->syncStudentMembership($subscription->student);
        }
    }

    private function completePlanChangeOnPayment(Subscription $subscription, Payment $payment): void
    {
        $targetPlan = $payment->target_plan_id ? Plan::find($payment->target_plan_id) : null;
        if (! $targetPlan) {
            return;
        }

        $this->applyPlanChangeImmediately($subscription, $targetPlan);
    }

    private function applyPlanChangeImmediately(Subscription $subscription, Plan $targetPlan): Subscription
    {
        $period = $this->calculatePlanChangePeriod($subscription, $targetPlan);

        $subscription->fill([
            'plan_id' => $targetPlan->id,
            'plan_name' => $targetPlan->name,
            'plan_category' => $targetPlan->category,
            'amount' => $targetPlan->price,
            'start_date' => $period['start'],
            'end_date' => $period['end'],
        ]);

        $subscription->update([
            'plan_id' => $targetPlan->id,
            'plan_name' => $targetPlan->name,
            'plan_category' => $targetPlan->category,
            'amount' => $targetPlan->price,
            'start_date' => $period['start'],
            'end_date' => $period['end'],
            'status' => $this->resolveExpiryStatus($subscription, $targetPlan),
        ]);

        if ($subscription->student) {
            $this->syncStudentMembership($subscription->student);
        }

        return $subscription->fresh(['student']);
    }

    /**
     * New plan period from today plus remaining days carried from the old plan.
     *
     * @return array{start: string, end: string, remaining_days: int}
     */
    private function calculatePlanChangePeriod(Subscription $subscription, Plan $targetPlan): array
    {
        $today = now()->startOfDay();
        $currentEnd = $subscription->end_date?->copy()->startOfDay();

        $remainingDays = 0;
        if ($currentEnd && $currentEnd->greaterThanOrEqualTo($today)) {
            $remainingDays = (int) $today->diffInDays($currentEnd);
        }

        $start = ($currentEnd && $currentEnd->greaterThanOrEqualTo($today))
            ? ($subscription->start_date?->toDateString() ?? $today->toDateString())
            : $today->toDateString();

        $planEnd = Carbon::parse(PlanDuration::endDateForPlan($today, $targetPlan));
        $newEnd = $planEnd->copy()->addDays($remainingDays);

        return [
            'start' => $start,
            'end' => $newEnd->toDateString(),
            'remaining_days' => $remainingDays,
        ];
    }

    public function refreshExpiryStatus(Subscription $subscription): Subscription
    {
        $subscription->loadMissing('plan');
        $status = $this->resolveExpiryStatus($subscription, $subscription->plan);

        if ($subscription->status !== $status) {
            $subscription->update(['status' => $status]);
        }

        return $subscription->fresh(['plan', 'student']);
    }

    private function resolveExpiryStatus(Subscription $subscription, ?Plan $plan = null): SubscriptionStatus
    {
        $today = now()->startOfDay();
        $end = $subscription->end_date?->copy()->startOfDay();

        if (! $end) {
            return SubscriptionStatus::Active;
        }

        if ($end->lessThan($today)) {
            return SubscriptionStatus::Expired;
        }

        if ($this->isExpiringSoonWindow($subscription, $plan, $today, $end)) {
            return SubscriptionStatus::ExpiringSoon;
        }

        return SubscriptionStatus::Active;
    }

    private function isExpiringSoonWindow(
        Subscription $subscription,
        ?Plan $plan,
        Carbon $today,
        Carbon $end,
    ): bool {
        $durationDays = $this->resolvePlanDurationDays($subscription, $plan);

        if ($durationDays <= 5) {
            return $today->equalTo($end);
        }

        return $today->greaterThanOrEqualTo($end->copy()->subDays(5));
    }

    private function resolvePlanDurationDays(Subscription $subscription, ?Plan $plan = null): int
    {
        $plan ??= $subscription->relationLoaded('plan')
            ? $subscription->plan
            : ($subscription->plan_id ? Plan::find($subscription->plan_id) : null);

        if ($plan && (int) $plan->duration_days > 0) {
            return (int) $plan->duration_days;
        }

        $start = $subscription->start_date?->copy()->startOfDay();
        $end = $subscription->end_date?->copy()->startOfDay();
        if ($start && $end && $end->greaterThanOrEqualTo($start)) {
            return (int) $start->diffInDays($end) + 1;
        }

        $defaults = PlanCategoryDefaults::durations($subscription->plan_category ?? 'Monthly');

        return (int) ($defaults['duration_days'] ?? 30);
    }

    /**
     * @return array{start: string, end: string, still_active: bool}
     */
    private function resolvePeriodOnPayment(Subscription $subscription, ?Plan $plan = null, ?string $action = null): array
    {
        $action = $this->normalizeSubscriptionAction($subscription, $action);

        if ($action === 'renew') {
            return $this->calculateRenewalPeriod($subscription, $plan);
        }

        return $this->calculateNewSubscriptionPeriod($subscription, $plan);
    }

    private function normalizeSubscriptionAction(Subscription $subscription, ?string $action): string
    {
        if (in_array($action, ['new', 'renew', 'upgrade', 'downgrade'], true)) {
            return $action;
        }

        $start = $subscription->start_date?->toDateString();
        $end = $subscription->end_date?->toDateString();

        if ($start && $end && $end <= $start) {
            return 'new';
        }

        return 'renew';
    }

    /**
     * First subscription payment — apply plan duration from start (no stacking).
     *
     * @return array{start: string, end: string, still_active: bool}
     */
    private function calculateNewSubscriptionPeriod(Subscription $subscription, ?Plan $plan = null): array
    {
        $plan ??= $subscription->plan_id ? Plan::find($subscription->plan_id) : null;
        $start = Carbon::parse($subscription->start_date)->startOfDay();

        return [
            'start' => $start->toDateString(),
            'end' => $plan
                ? PlanDuration::endDateForPlan($start, $plan)
                : PlanDuration::endDate(
                    $start,
                    durationDays: PlanCategoryDefaults::durations($subscription->plan_category ?? 'Monthly')['duration_days'] ?? 30,
                ),
            'still_active' => false,
        ];
    }

    /**
     * @return array{start: string, end: string, still_active: bool}
     */
    private function calculateRenewalPeriod(Subscription $subscription, ?Plan $plan = null): array
    {
        $plan ??= $subscription->plan_id ? Plan::find($subscription->plan_id) : null;

        $today = now()->startOfDay();
        $currentEnd = $subscription->end_date?->copy()->startOfDay();
        $originalStart = $subscription->start_date?->copy()->startOfDay() ?? $today->copy();
        $stillActive = $currentEnd && $currentEnd->greaterThanOrEqualTo($today);

        if ($stillActive) {
            $start = $originalStart->toDateString();
            $periodStart = $currentEnd->copy()->addDay();
        } else {
            $start = $today->toDateString();
            $periodStart = $today->copy();
        }

        $end = $plan
            ? PlanDuration::endDateForPlan($periodStart, $plan)
            : PlanDuration::endDate(
                $periodStart,
                durationDays: PlanCategoryDefaults::durations($subscription->plan_category ?? 'Monthly')['duration_days'] ?? 30,
            );

        if ($currentEnd && Carbon::parse($end)->lessThanOrEqualTo($currentEnd)) {
            $periodStart = $currentEnd->copy()->addDay();
            $end = $plan
                ? PlanDuration::endDateForPlan($periodStart, $plan)
                : PlanDuration::endDate(
                    $periodStart,
                    durationDays: PlanCategoryDefaults::durations($subscription->plan_category ?? 'Monthly')['duration_days'] ?? 30,
                );
        }

        return [
            'start' => $start,
            'end' => $end,
            'still_active' => $stillActive,
        ];
    }

    public function createRenewalPayment(
        Subscription $subscription,
        float|string $amount,
        string $method = 'Pending',
        string $subscriptionAction = 'new',
        ?int $targetPlanId = null,
    ): Payment {
        $existing = Payment::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->whereNull('admission_id')
            ->first();

        if ($existing) {
            $existing->update([
                'amount' => $amount,
                'method' => $method,
                'subscription_action' => $subscriptionAction,
                'target_plan_id' => $targetPlanId,
            ]);

            return $existing->fresh();
        }

        return Payment::create([
            'payment_code' => $this->nextPaymentCode(),
            'student_id' => $subscription->student_id,
            'subscription_id' => $subscription->id,
            'subscription_action' => $subscriptionAction,
            'target_plan_id' => $targetPlanId,
            'amount' => $amount,
            'method' => $method,
            'status' => 'pending',
        ]);
    }

    public function cancelSubscription(Subscription $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->loadMissing(['student', 'plan']);
            $subscription->update(['status' => SubscriptionStatus::Cancelled]);

            $paymentIds = Payment::where('subscription_id', $subscription->id)->pluck('id');

            $pendingCancelled = Payment::where('subscription_id', $subscription->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $refundAmount = 0.0;
            $refundPayment = null;

            $latestPaid = Payment::where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->latest('paid_at')
                ->latest('id')
                ->first();

            if ($latestPaid) {
                $refundAmount = $this->calculateProratedRefund($subscription, $latestPaid);

                if ($refundAmount > 0) {
                    $latestPaid->update([
                        'refund_amount' => $refundAmount,
                        'refund_status' => 'pending',
                        'status' => 'refund_pending',
                    ]);
                    $refundPayment = $latestPaid->fresh();
                }
            }

            if ($paymentIds->isNotEmpty()) {
                Invoice::whereIn('payment_id', $paymentIds)
                    ->where('document_type', 'payment')
                    ->whereIn('status', ['pending', 'paid'])
                    ->update(['status' => 'cancelled']);
            }

            if ($refundPayment && $refundAmount > 0) {
                app(InvoiceService::class)->createForRefund($refundPayment);
            }

            if ($subscription->student) {
                $this->syncStudentMembership($subscription->student->fresh());
            }

            return [
                'subscription' => $subscription->fresh(['student']),
                'pending_cancelled' => $pendingCancelled,
                'refund_amount' => $refundAmount,
                'refund_payment' => $refundPayment,
            ];
        });
    }

    public function calculateProratedRefund(Subscription $subscription, ?Payment $payment = null): float
    {
        $today = now()->startOfDay();
        $start = $subscription->start_date?->copy()->startOfDay();
        $end = $subscription->end_date?->copy()->startOfDay();

        if (! $start || ! $end || $end->lessThan($today)) {
            return 0.0;
        }

        $payment ??= Payment::where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->latest('id')
            ->first();

        if (! $payment) {
            return 0.0;
        }

        $totalDays = max(1, (int) $start->diffInDays($end) + 1);
        $remainingDays = max(0, (int) $today->diffInDays($end) + 1);

        if ($remainingDays <= 0) {
            return 0.0;
        }

        return round(((float) $payment->amount) * ($remainingDays / $totalDays), 2);
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        if (! $this->canDeleteSubscription($subscription)) {
            throw new \RuntimeException('Cannot delete a subscription after payment has been collected. Cancel the subscription instead.');
        }

        DB::transaction(function () use ($subscription) {
            $student = $subscription->student;

            $linkedAdmissionIds = Admission::where('subscription_id', $subscription->id)->pluck('id');

            $paymentIds = Payment::where(function ($query) use ($subscription, $linkedAdmissionIds) {
                    $query->where('subscription_id', $subscription->id);
                    if ($linkedAdmissionIds->isNotEmpty()) {
                        $query->orWhereIn('admission_id', $linkedAdmissionIds);
                    }
                })
                ->pluck('id');

            if ($paymentIds->isNotEmpty()) {
                Invoice::whereIn('payment_id', $paymentIds)
                    ->delete();

                Payment::whereIn('id', $paymentIds)
                    ->delete();
            }

            Admission::where('subscription_id', $subscription->id)
                ->update(['subscription_id' => null]);

            $subscription->delete();

            if ($student) {
                $this->syncStudentMembership($student->fresh());
            }
        });
    }

    public function canDeleteSubscription(Subscription $subscription): bool
    {
        return ! $this->hasCollectedPayment($subscription);
    }

    private function hasCollectedPayment(Subscription $subscription): bool
    {
        $linkedAdmissionIds = Admission::where('subscription_id', $subscription->id)->pluck('id');

        return Payment::where(function ($query) use ($subscription, $linkedAdmissionIds) {
            $query->where('subscription_id', $subscription->id);
            if ($linkedAdmissionIds->isNotEmpty()) {
                $query->orWhereIn('admission_id', $linkedAdmissionIds);
            }
        })
            ->whereIn('status', ['paid', 'refund_pending', 'refunded'])
            ->exists();
    }

    public function syncStudentMembership(Student $student): void
    {
        $this->syncStudentMembershipFromSubscriptions($student->fresh());
        $this->biometricAccess->syncStudentAccess($student->fresh());
    }

    private function syncStudentMembershipFromSubscriptions(Student $student): void
    {
        if ($student->status === StudentStatus::Blacklisted) {
            return;
        }

        $subscription = Subscription::query()
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            $student->update([
                'plan_name' => null,
                'valid_from' => null,
                'expiry' => null,
                'status' => StudentStatus::Pending,
            ]);

            return;
        }

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            $student->update([
                'plan_name' => null,
                'valid_from' => null,
                'expiry' => null,
                'status' => StudentStatus::Expired,
            ]);

            return;
        }

        $student->update([
            'plan_name' => $subscription->plan_name,
            'valid_from' => $subscription->start_date?->toDateString(),
            'expiry' => $subscription->end_date?->toDateString(),
            'status' => $this->studentStatusFromSubscription($subscription),
        ]);
    }

    private function studentStatusFromSubscription(Subscription $subscription): StudentStatus
    {
        $today = now()->toDateString();
        $endDate = $subscription->end_date?->toDateString();

        return match ($subscription->status) {
            SubscriptionStatus::Active, SubscriptionStatus::Renewed, SubscriptionStatus::ExpiringSoon =>
                ($endDate && $endDate >= $today) ? StudentStatus::Active : StudentStatus::Expired,
            SubscriptionStatus::Pending =>
                ($endDate && $endDate >= $today) ? StudentStatus::Active : StudentStatus::Pending,
            SubscriptionStatus::Paused => StudentStatus::Pending,
            SubscriptionStatus::Expired, SubscriptionStatus::Cancelled => StudentStatus::Expired,
            default => StudentStatus::Pending,
        };
    }

    public function createManual(array $data): Subscription
    {
        if (! empty($data['plan_id'])) {
            $plan = Plan::find($data['plan_id']);
            if ($plan) {
                $data['plan_category'] = $plan->category;
            }
        }

        // Do not extend membership until payment is collected.
        $data['end_date'] = $data['start_date'];

        $subscription = Subscription::create([
            ...$data,
            'subscription_code' => $this->nextSubscriptionCode(),
            'status' => SubscriptionStatus::Pending,
            'membership_source' => 'new',
            'auto_renew' => false,
        ]);

        $this->createRenewalPayment($subscription->fresh(), $data['amount'], 'Cash', 'new');

        return $subscription;
    }

    private function nextSubscriptionCode(): string
    {
        $year = date('Y');
        $last = Subscription::where('subscription_code', 'like', "SUB-{$year}-%")
            ->orderByDesc('id')
            ->value('subscription_code');
        $num = $last ? (int) substr($last, -3) + 1 : 1;

        return 'SUB-'.$year.'-'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }

    private function nextPaymentCode(): string
    {
        $year = date('Y');
        $last = Payment::where('payment_code', 'like', "PAY-{$year}-%")
            ->orderByDesc('id')
            ->value('payment_code');
        $num = $last ? (int) substr($last, -3) + 1 : 1;

        return 'PAY-'.$year.'-'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }
}
