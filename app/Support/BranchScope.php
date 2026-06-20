<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    public static function apply(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if ($user && $user->hasRole('branch_manager') && $user->branch_id) {
            $query->where($column, $user->branch_id);
        }

        return $query;
    }

    public static function branchId(?User $user): ?int
    {
        if ($user && $user->hasRole('branch_manager') && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return null;
    }
}
