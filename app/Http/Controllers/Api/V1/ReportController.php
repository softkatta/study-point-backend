<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\AppSettingsService;
use App\Services\FinancialSummaryService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private FinancialSummaryService $financialSummary,
    ) {}

    public function generate(string $type, Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'status', 'branch_id', 'category']);
        $branchId = BranchScope::branchId($request->user());
        if (! $branchId && ! empty($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $branchId = (int) $filters['branch_id'];
        }

        return match ($type) {
            'revenue' => ApiResponse::success($this->revenueReport($branchId, $filters)),
            'admissions' => ApiResponse::success($this->admissionsReport($branchId, $filters)),
            'students' => ApiResponse::success($this->studentReport($branchId, $filters)),
            'subscriptions' => ApiResponse::success($this->subscriptionReport($branchId, $filters)),
            'payments' => ApiResponse::success($this->paymentReport($branchId, $filters)),
            'expenses' => ApiResponse::success($this->expenseReport($branchId, $filters)),
            'attendance' => ApiResponse::success($this->attendanceSummary($branchId, $filters)),
            'gst' => ApiResponse::success($this->gstReport($branchId, $filters)),
            'financial' => ApiResponse::success($this->financialReport($branchId, $filters)),
            default => ApiResponse::error('Unknown report type', 404),
        };
    }

    public function exportExcel(string $type): JsonResponse
    {
        return ApiResponse::success(['type' => $type, 'format' => 'excel', 'ready' => true]);
    }

    public function exportPdf(string $type): JsonResponse
    {
        return ApiResponse::success(['type' => $type, 'format' => 'pdf', 'ready' => true]);
    }

    /** @param  array<string, mixed>  $filters */
    private function applyDateFilter(Builder $query, string $column, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    private function revenueReport(?int $branchId, array $filters): array
    {
        $query = Payment::query()
            ->select('students.branch_id', DB::raw('SUM(payments.amount) as total'), DB::raw('COUNT(*) as count'))
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->where('payments.status', 'paid');

        $this->applyDateFilter($query, 'payments.paid_at', $filters);

        if ($branchId) {
            $query->where('students.branch_id', $branchId);
        }

        $query->groupBy('students.branch_id');
        $rows = $query->get();
        $branches = Branch::whereIn('id', $rows->pluck('branch_id'))->pluck('name', 'id');

        $exportRows = $rows->map(fn ($r) => [
            'branch' => $branches[$r->branch_id] ?? 'Unknown',
            'revenue' => (float) $r->total,
            'payments' => (int) $r->count,
        ])->values()->all();

        return [
            'rows' => $exportRows,
            'total' => array_sum(array_column($exportRows, 'revenue')),
            'summary' => $this->financialSummary->summarize($branchId, $filters),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function financialReport(?int $branchId, array $filters): array
    {
        return [
            'summary' => $this->financialSummary->summarize($branchId, $filters),
            'rows' => [],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function admissionsReport(?int $branchId, array $filters): array
    {
        $trendQuery = Admission::query()
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month');

        if ($branchId) {
            $trendQuery->where('branch_id', $branchId);
        }

        $listQuery = Admission::with('branch')->latest();
        if ($branchId) {
            $listQuery->where('branch_id', $branchId);
        }
        $this->applyDateFilter($listQuery, 'created_at', $filters);
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $listQuery->where('status', $filters['status']);
        }

        $rows = $listQuery->limit(500)->get()->map(fn ($a) => [
            'id' => $a->admission_code,
            'name' => $a->name,
            'status' => $a->status->value ?? (string) $a->status,
            'branch' => $a->branch?->name ?? '—',
            'date' => $a->created_at?->toDateString(),
            'plan' => $a->plan_name ?? $a->plan ?? '—',
        ])->values()->all();

        return [
            'trend' => $trendQuery->get()->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count])->values()->all(),
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function studentReport(?int $branchId, array $filters): array
    {
        $query = Student::with('branch');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $rows = $query->latest()->limit(500)->get()->map(fn ($s) => [
            'id' => $s->student_code,
            'name' => $s->name,
            'status' => $s->status->value ?? (string) $s->status,
            'branch' => $s->branch?->name ?? '—',
            'plan' => $s->plan_name ?? '—',
            'expiry' => $s->expiry?->toDateString(),
        ])->values()->all();

        $stats = [
            'total' => count($rows),
            'active' => collect($rows)->where('status', 'active')->count(),
            'expired' => collect($rows)->where('status', 'expired')->count(),
            'pending' => collect($rows)->where('status', 'pending')->count(),
            'blacklisted' => collect($rows)->where('status', 'blacklisted')->count(),
        ];

        return ['stats' => $stats, 'rows' => $rows];
    }

    /** @param  array<string, mixed>  $filters */
    private function subscriptionReport(?int $branchId, array $filters): array
    {
        $query = Subscription::with(['student.branch'])->latest();
        if ($branchId) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilter($query, 'start_date', $filters);

        $rows = $query->limit(500)->get()->map(fn ($s) => [
            'id' => $s->subscription_code ?? (string) $s->id,
            'student' => $s->student?->name ?? '—',
            'plan' => $s->plan_name ?? '—',
            'status' => $s->status->value ?? (string) $s->status,
            'branch' => $s->student?->branch?->name ?? '—',
            'start_date' => $s->start_date?->toDateString(),
            'end_date' => $s->end_date?->toDateString(),
            'amount' => (float) $s->amount,
        ])->values()->all();

        return [
            'rows' => $rows,
            'stats' => [
                'active' => collect($rows)->where('status', 'active')->count(),
                'expired' => collect($rows)->where('status', 'expired')->count(),
                'cancelled' => collect($rows)->where('status', 'cancelled')->count(),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function paymentReport(?int $branchId, array $filters): array
    {
        $query = Payment::with(['student.branch'])->latest('paid_at');
        if ($branchId) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilter($query, 'created_at', $filters);

        $rows = $query->limit(500)->get()->map(fn ($p) => [
            'id' => $p->payment_code,
            'student' => $p->student?->name ?? '—',
            'amount' => (float) $p->amount,
            'method' => $p->method ?? '—',
            'status' => $p->status,
            'subscription_action' => $p->subscription_action ?? '—',
            'branch' => $p->student?->branch?->name ?? '—',
            'date' => $p->paid_at?->toDateString() ?? $p->created_at?->toDateString(),
        ])->values()->all();

        $summary = $this->financialSummary->summarize($branchId, $filters);

        return [
            'rows' => $rows,
            'stats' => [
                'collected' => $summary['total_income'],
                'pending' => $summary['pending_income'],
                'failed' => collect($rows)->where('status', 'failed')->sum('amount'),
                'refunded' => $summary['refunds_given'],
                'renewal_income' => $summary['renewal_income'],
                'net_profit' => $summary['net_profit'],
            ],
            'summary' => $summary,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function expenseReport(?int $branchId, array $filters): array
    {
        $query = Expense::with('branch')->latest('expense_date');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }
        $this->applyDateFilter($query, 'expense_date', $filters);

        $items = $query->limit(500)->get();
        $rows = $items->map(fn ($e) => [
            'id' => 'EXP'.str_pad((string) $e->id, 3, '0', STR_PAD_LEFT),
            'title' => $e->title,
            'category' => $e->category,
            'amount' => (float) $e->amount,
            'branch' => $e->branch?->name ?? '—',
            'status' => $e->status,
            'date' => $e->expense_date?->toDateString(),
        ])->values()->all();

        $approved = $items->where('status', 'approved');
        $byCategory = $approved->groupBy('category')->map(fn ($g, $cat) => [
            'category' => $cat,
            'amount' => (float) $g->sum('amount'),
            'count' => $g->count(),
        ])->values()->all();

        $byBranch = $approved->groupBy(fn ($e) => $e->branch?->name ?? 'Unassigned')->map(fn ($g, $branch) => [
            'branch' => $branch,
            'amount' => (float) $g->sum('amount'),
        ])->values()->all();

        return [
            'rows' => $rows,
            'stats' => [
                'total_approved' => (float) $approved->sum('amount'),
                'pending' => $items->where('status', 'pending')->count(),
                'rejected' => $items->where('status', 'rejected')->count(),
            ],
            'by_category' => $byCategory,
            'by_branch' => $byBranch,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function attendanceSummary(?int $branchId, array $filters): array
    {
        $today = now()->toDateString();

        $studentsQuery = Student::query()->where('status', 'active');
        if ($branchId) {
            $studentsQuery->where('branch_id', $branchId);
        }
        $activeStudents = (clone $studentsQuery)->count();

        $logsQuery = AttendanceLog::query()->whereDate('check_in', $today);
        if ($branchId) {
            $logsQuery->where('branch_id', $branchId);
        }

        $presentToday = (clone $logsQuery)->distinct('student_id')->count('student_id');
        $avgHours = (float) (clone $logsQuery)->whereNotNull('hours')->avg('hours');

        $weeklyQuery = AttendanceLog::query()
            ->select(DB::raw("DATE_FORMAT(check_in, '%Y-%m-%d') as day"), DB::raw('COUNT(DISTINCT student_id) as present'))
            ->where('check_in', '>=', now()->subDays(6)->startOfDay())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('day')
            ->orderBy('day');

        $this->applyDateFilter($weeklyQuery, 'check_in', $filters);

        $weekly = $weeklyQuery->get()->map(fn ($r) => [
            'day' => $r->day,
            'present' => (int) $r->present,
        ])->values()->all();

        return [
            'present_today' => $presentToday,
            'absent_today' => max(0, $activeStudents - $presentToday),
            'active_students' => $activeStudents,
            'avg_hours' => round($avgHours, 1),
            'weekly' => $weekly,
            'rows' => $weekly,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function gstReport(?int $branchId, array $filters): array
    {
        $gst = $this->settings->gst();
        $query = Invoice::with(['student.branch'])->where('status', '!=', 'cancelled');

        if ($branchId) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }
        $this->applyDateFilter($query, 'issued_at', $filters);
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $invoices = $query->limit(500)->get();
        $rows = $invoices->map(fn ($i) => [
            'id' => $i->invoice_code,
            'student' => $i->student?->name ?? '—',
            'branch' => $i->student?->branch?->name ?? '—',
            'taxable' => (float) $i->amount,
            'gst' => (float) $i->gst_amount,
            'total' => (float) $i->total,
            'status' => $i->status,
            'date' => $i->issued_at?->toDateString(),
        ])->values()->all();

        $paid = $invoices->where('status', 'paid');

        return [
            'gstin' => $gst['gstin'],
            'gst_rate' => (float) $gst['gst_rate'],
            'collected' => (float) $paid->sum('gst_amount'),
            'pending' => (float) $invoices->where('status', 'pending')->sum('gst_amount'),
            'taxable_value' => (float) $paid->sum('amount'),
            'rows' => $rows,
        ];
    }
}
