<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinancialSummaryService
{
    /** Payments that were actually collected (paid_at set), including later refunded ones. */
    private function collectedPaymentsQuery(?int $branchId, array $filters): Builder
    {
        $query = $this->scopedPayments($branchId)
            ->whereNotNull('paid_at')
            ->whereIn('status', ['paid', 'refunded', 'refund_pending']);

        $this->applyDateFilter($query, 'paid_at', $filters);

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    public function summarize(?int $branchId, array $filters = []): array
    {
        $collectedQuery = $this->collectedPaymentsQuery($branchId, $filters);

        $totalIncome = (float) (clone $collectedQuery)->sum('amount');
        $renewalIncome = (float) (clone $collectedQuery)->where('subscription_action', 'renew')->sum('amount');
        $newIncome = (float) (clone $collectedQuery)->where(function (Builder $q) {
            $q->whereNull('subscription_action')
                ->orWhereIn('subscription_action', ['new', '']);
        })->sum('amount');
        $upgradeIncome = (float) (clone $collectedQuery)->where('subscription_action', 'upgrade')->sum('amount');

        $pendingQuery = $this->scopedPayments($branchId)->where('status', 'pending');
        $this->applyDateFilter($pendingQuery, 'created_at', $filters);
        $pendingIncome = (float) $pendingQuery->sum('amount');
        $pendingCount = (int) (clone $pendingQuery)->count();

        $refundsGivenQuery = $this->scopedPayments($branchId)->where('refund_status', 'received');
        $this->applyDateFilter($refundsGivenQuery, 'refunded_at', $filters);
        $refundsGiven = (float) $refundsGivenQuery->sum(DB::raw('COALESCE(refund_amount, amount, 0)'));

        $refundsPendingQuery = $this->scopedPayments($branchId)->where('refund_status', 'pending');
        $refundsPending = (float) $refundsPendingQuery->sum(DB::raw('COALESCE(refund_amount, amount, 0)'));

        $expenseQuery = Expense::query()->where('status', 'approved');
        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }
        $this->applyDateFilter($expenseQuery, 'expense_date', $filters);
        $totalExpenses = (float) $expenseQuery->sum('amount');

        $netIncome = round($totalIncome - $refundsGiven, 2);
        $netProfit = round($netIncome - $totalExpenses, 2);

        return [
            'total_income' => round($totalIncome, 2),
            'pending_income' => round($pendingIncome, 2),
            'pending_count' => $pendingCount,
            'renewal_income' => round($renewalIncome, 2),
            'new_income' => round($newIncome, 2),
            'upgrade_income' => round($upgradeIncome, 2),
            'refunds_given' => round($refundsGiven, 2),
            'refunds_pending' => round($refundsPending, 2),
            'net_income' => $netIncome,
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => $netProfit,
        ];
    }

    private function scopedPayments(?int $branchId): Builder
    {
        $query = Payment::query();
        if ($branchId) {
            $query->whereHas('student', fn (Builder $q) => $q->where('branch_id', $branchId));
        }

        return $query;
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
}
