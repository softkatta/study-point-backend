<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\FinancialSummaryService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private FinancialSummaryService $financialSummary) {}

    public function stats(Request $request): JsonResponse
    {
        $branchId = BranchScope::branchId($request->user());

        $studentQuery = Student::query();
        $admissionQuery = Admission::query();
        $subscriptionQuery = Subscription::query();

        if ($branchId) {
            $studentQuery->where('branch_id', $branchId);
            $admissionQuery->where('branch_id', $branchId);
            $subscriptionQuery->where('branch_id', $branchId);
        }

        $financial = $this->financialSummary->summarize($branchId);

        return ApiResponse::success([
            'students' => [
                'total' => (clone $studentQuery)->count(),
                'active' => (clone $studentQuery)->where('status', 'active')->count(),
                'pending' => (clone $studentQuery)->where('status', 'pending')->count(),
                'expired' => (clone $studentQuery)->where('status', 'expired')->count(),
                'blocked' => (clone $studentQuery)->where('status', 'blacklisted')->count(),
            ],
            'admissions' => [
                'total' => (clone $admissionQuery)->count(),
                'pending' => (clone $admissionQuery)->where('status', 'pending')->count(),
                'verified' => (clone $admissionQuery)->where('status', 'verified')->count(),
                'active' => (clone $admissionQuery)->where('status', 'active')->count(),
            ],
            'subscriptions' => [
                'total' => (clone $subscriptionQuery)->count(),
                'active' => (clone $subscriptionQuery)->whereIn('status', ['active', 'renewed'])->count(),
                'expiring_soon' => (clone $subscriptionQuery)->where('status', 'expiring_soon')->count(),
            ],
            'payments' => [
                'collected' => $financial['total_income'],
                'pending' => $financial['pending_count'],
                'pending_amount' => $financial['pending_income'],
                'renewal_income' => $financial['renewal_income'],
                'refunds_given' => $financial['refunds_given'],
                'refunds_pending' => $financial['refunds_pending'],
                'net_income' => $financial['net_income'],
                'expenses' => $financial['total_expenses'],
                'net_profit' => $financial['net_profit'],
            ],
            'financial' => $financial,
            'branch_id' => $branchId,
        ]);
    }

    public function charts(Request $request): JsonResponse
    {
        $branchId = BranchScope::branchId($request->user());

        $paymentQuery = Payment::query()
            ->whereNotNull('paid_at')
            ->whereIn('status', ['paid', 'refunded', 'refund_pending']);
        if ($branchId) {
            $paymentQuery->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }

        $monthlyRevenue = (clone $paymentQuery)
            ->selectRaw("DATE_FORMAT(paid_at, '%b') as month, SUM(amount) as revenue")
            ->where('paid_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(paid_at, '%Y-%m'), DATE_FORMAT(paid_at, '%b')")
            ->orderByRaw("MIN(paid_at)")
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'revenue' => (float) $r->revenue]);

        $admissionQuery = Admission::query();
        if ($branchId) {
            $admissionQuery->where('branch_id', $branchId);
        }

        $admissionsByMonth = (clone $admissionQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')")
            ->orderByRaw("MIN(created_at)")
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count]);

        $studentQuery = Student::query()->where('status', 'active');
        if ($branchId) {
            $studentQuery->where('branch_id', $branchId);
        }

        $planCounts = (clone $studentQuery)
            ->selectRaw('plan_name as name, COUNT(*) as count')
            ->whereNotNull('plan_name')
            ->groupBy('plan_name')
            ->orderByDesc('count')
            ->get();

        $planTotal = $planCounts->sum('count');
        $planDistribution = $planCounts->map(fn ($r) => [
            'name' => $r->name,
            'count' => (int) $r->count,
            'value' => $planTotal > 0 ? (int) round($r->count / $planTotal * 100) : 0,
        ])->values();

        $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weeklyAdmissions = collect(range(6, 0))->map(function (int $daysAgo) use ($branchId, $dayLabels) {
            $date = now()->subDays($daysAgo);
            $query = Admission::query()->whereDate('created_at', $date->toDateString());
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return [
                'day' => $dayLabels[$date->dayOfWeekIso - 1],
                'admissions' => $query->count(),
            ];
        })->values();

        $branchPerformance = [];
        if (! $branchId) {
            $branchPerformance = Branch::query()
                ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
                ->get()
                ->map(function (Branch $b) {
                    $revenue = Payment::query()
                        ->whereNotNull('paid_at')
                        ->whereIn('status', ['paid', 'refunded', 'refund_pending'])
                        ->whereHas('student', fn ($q) => $q->where('branch_id', $b->id))
                        ->sum('amount');

                    return [
                        'name' => $b->name,
                        'students' => $b->students_count,
                        'revenue' => (float) $revenue,
                    ];
                })
                ->sortByDesc('revenue')
                ->values();
        }

        return ApiResponse::success([
            'revenue' => $monthlyRevenue,
            'admissions' => $admissionsByMonth,
            'plan_distribution' => $planDistribution,
            'weekly_admissions' => $weeklyAdmissions,
            'branches' => $branchPerformance,
        ]);
    }

    public function recentAdmissions(Request $request): JsonResponse
    {
        $query = Admission::with(['branch', 'plan'])->latest()->limit(10);
        if ($branchId = BranchScope::branchId($request->user())) {
            $query->where('branch_id', $branchId);
        }

        return ApiResponse::success($query->get()->map(fn ($a) => [
            'id' => $a->admission_code,
            'name' => $a->name,
            'branch' => $a->branch?->name,
            'plan' => $a->plan_name,
            'status' => $a->status->value ?? $a->status,
            'date' => $a->created_at?->toDateString(),
        ]));
    }
}
