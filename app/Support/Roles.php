<?php

namespace App\Support;

final class Roles
{
    public const SUPER_ADMIN = 'super_admin';

    public const BRANCH_MANAGER = 'branch_manager';

    public const STAFF = 'staff';

    public const RECEPTIONIST = 'receptionist';

    public const ATTENDANCE_OPERATOR = 'attendance_operator';

    public const STUDENT = 'student';

    public const ALL = [
        self::SUPER_ADMIN,
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
        self::STUDENT,
    ];

    /** Roles that can access the branch portal. */
    public const BRANCH_PORTAL = [
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
    ];

    /** Roles that can mark attendance. */
    public const ATTENDANCE_STAFF = [
        self::SUPER_ADMIN,
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
    ];

    /** Staff roles assignable from admin (includes student portal role). */
    public const ASSIGNABLE = [
        self::SUPER_ADMIN,
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
        self::STUDENT,
    ];

    /** All roles editable in the permission matrix UI. */
    public const MANAGEABLE = [
        self::SUPER_ADMIN,
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
        self::STUDENT,
    ];

    /** Roles that require a branch when assigned. */
    public const BRANCH_BOUND = [
        self::BRANCH_MANAGER,
        self::STAFF,
        self::RECEPTIONIST,
        self::ATTENDANCE_OPERATOR,
    ];

    public static function requiresBranch(string $role): bool
    {
        return in_array($role, self::BRANCH_BOUND, true);
    }

    /** Roles that cannot be deleted from admin. */
    public const PROTECTED = [
        self::SUPER_ADMIN,
        self::STUDENT,
    ];

    public static function isProtected(string $role): bool
    {
        return in_array($role, self::PROTECTED, true);
    }

    /** Super Admin permissions are fixed to all permissions. */
    public static function permissionsLocked(string $role): bool
    {
        return $role === self::SUPER_ADMIN;
    }

    /** @deprecated Use permissionsLocked() */
    public static function isImmutable(string $role): bool
    {
        return self::permissionsLocked($role);
    }

    /** @param list<string> $roles */
    public static function requiresBranchForRoles(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return \App\Models\Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $roles)
            ->where('requires_branch', true)
            ->exists();
    }

    /** @param list<string> $roles */
    public static function primary(array $roles): ?string
    {
        foreach (self::ALL as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? null;
    }
}
