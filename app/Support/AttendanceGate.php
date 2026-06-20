<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Support\ApiResponse;

class AttendanceGate
{
    /** @var list<string> */
    public const STAFF_ROLES = ['super_admin', 'branch_manager', 'staff', 'receptionist', 'attendance_operator'];

    public static function canMark(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(self::STAFF_ROLES);
    }

    public static function ensure(?User $user, ?Student $student = null): void
    {
        if (! self::canMark($user)) {
            throw new HttpResponseException(
                ApiResponse::error('Only admin, branch manager, or staff can mark attendance.', 403)
            );
        }

        if ($student && ($branchId = BranchScope::branchId($user))) {
            if ((int) $student->branch_id !== $branchId) {
                throw new HttpResponseException(
                    ApiResponse::error('This student does not belong to your branch.', 403)
                );
            }
        }
    }
}
