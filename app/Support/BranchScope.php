<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    public static function branchId(?User $user): ?int
    {
        if (! $user?->branch_id || $user->hasRole(Roles::SUPER_ADMIN)) {
            return null;
        }

        if ($user->hasAnyRole(Roles::BRANCH_BOUND)) {
            return (int) $user->branch_id;
        }

        return null;
    }

    public static function isBranchBound(?User $user): bool
    {
        return self::branchId($user) !== null;
    }

    public static function apply(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if ($branchId = self::branchId($user)) {
            $query->where($column, $branchId);
        }

        return $query;
    }

    public static function applyToPayments(Builder $query, ?User $user): Builder
    {
        if ($branchId = self::branchId($user)) {
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('student', fn ($sq) => $sq->where('branch_id', $branchId))
                    ->orWhereHas('admission', fn ($aq) => $aq->where('branch_id', $branchId))
                    ->orWhereHas('subscription', fn ($subq) => $subq->where('branch_id', $branchId));
            });
        }

        return $query;
    }

    public static function authorize(?User $user, ?int $recordBranchId): void
    {
        $scoped = self::branchId($user);
        if ($scoped === null) {
            return;
        }

        if ($recordBranchId === null || (int) $recordBranchId !== $scoped) {
            abort(403, 'You can only access data for your assigned branch.');
        }
    }

    public static function authorizeModel(?User $user, object $model, string $column = 'branch_id'): void
    {
        $value = $model->{$column} ?? null;
        self::authorize($user, $value !== null ? (int) $value : null);
    }

    public static function authorizePayment(?User $user, Payment $payment): void
    {
        $payment->loadMissing(['student', 'admission', 'subscription']);

        $branchId = $payment->student?->branch_id
            ?? $payment->admission?->branch_id
            ?? $payment->subscription?->branch_id;

        self::authorize($user, $branchId !== null ? (int) $branchId : null);
    }

    /** @param  array<string, mixed>  $data */
    public static function forceBranchId(?User $user, array &$data, string $key = 'branch_id'): void
    {
        if ($branchId = self::branchId($user)) {
            $data[$key] = $branchId;
        }
    }
}
