<?php

namespace App\Support;

final class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_DELETE = 'users.delete';
    public const USERS_CHANGE_ROLE = 'users.change_role';
    public const PERMISSIONS_MANAGE = 'permissions.manage';

    public const STUDENTS_VIEW = 'students.view';
    public const STUDENTS_MANAGE = 'students.manage';

    public const ADMISSIONS_VIEW = 'admissions.view';
    public const ADMISSIONS_MANAGE = 'admissions.manage';
    public const ADMISSIONS_APPROVE = 'admissions.approve';

    public const SUBSCRIPTIONS_VIEW = 'subscriptions.view';
    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';

    public const PAYMENTS_VIEW = 'payments.view';
    public const PAYMENTS_COLLECT = 'payments.collect';
    public const PAYMENTS_REFUND = 'payments.refund';

    public const INVOICES_VIEW = 'invoices.view';
    public const INVOICES_MANAGE = 'invoices.manage';

    public const EXPENSES_VIEW = 'expenses.view';
    public const EXPENSES_MANAGE = 'expenses.manage';

    public const ATTENDANCE_VIEW = 'attendance.view';
    public const ATTENDANCE_MARK = 'attendance.mark';

    public const REPORTS_VIEW = 'reports.view';

    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_MANAGE = 'settings.manage';

    public const BRANCHES_VIEW = 'branches.view';
    public const BRANCHES_MANAGE = 'branches.manage';

    public const PLANS_VIEW = 'plans.view';
    public const PLANS_MANAGE = 'plans.manage';

    public const BIOMETRIC_VIEW = 'biometric.view';
    public const BIOMETRIC_MANAGE = 'biometric.manage';

    public const COMMUNICATIONS_VIEW = 'communications.view';
    public const COMMUNICATIONS_MANAGE = 'communications.manage';

    public const ALL = [
        self::DASHBOARD_VIEW,
        self::USERS_VIEW,
        self::USERS_CREATE,
        self::USERS_UPDATE,
        self::USERS_DELETE,
        self::USERS_CHANGE_ROLE,
        self::PERMISSIONS_MANAGE,
        self::STUDENTS_VIEW,
        self::STUDENTS_MANAGE,
        self::ADMISSIONS_VIEW,
        self::ADMISSIONS_MANAGE,
        self::ADMISSIONS_APPROVE,
        self::SUBSCRIPTIONS_VIEW,
        self::SUBSCRIPTIONS_MANAGE,
        self::PAYMENTS_VIEW,
        self::PAYMENTS_COLLECT,
        self::PAYMENTS_REFUND,
        self::INVOICES_VIEW,
        self::INVOICES_MANAGE,
        self::EXPENSES_VIEW,
        self::EXPENSES_MANAGE,
        self::ATTENDANCE_VIEW,
        self::ATTENDANCE_MARK,
        self::REPORTS_VIEW,
        self::SETTINGS_VIEW,
        self::SETTINGS_MANAGE,
        self::BRANCHES_VIEW,
        self::BRANCHES_MANAGE,
        self::PLANS_VIEW,
        self::PLANS_MANAGE,
        self::BIOMETRIC_VIEW,
        self::BIOMETRIC_MANAGE,
        self::COMMUNICATIONS_VIEW,
        self::COMMUNICATIONS_MANAGE,
    ];

    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        return [
            'Dashboard' => [self::DASHBOARD_VIEW],
            'Users & Access' => [
                self::USERS_VIEW,
                self::USERS_CREATE,
                self::USERS_UPDATE,
                self::USERS_DELETE,
                self::USERS_CHANGE_ROLE,
                self::PERMISSIONS_MANAGE,
            ],
            'Students' => [self::STUDENTS_VIEW, self::STUDENTS_MANAGE],
            'Admissions' => [self::ADMISSIONS_VIEW, self::ADMISSIONS_MANAGE, self::ADMISSIONS_APPROVE],
            'Subscriptions' => [self::SUBSCRIPTIONS_VIEW, self::SUBSCRIPTIONS_MANAGE],
            'Payments' => [self::PAYMENTS_VIEW, self::PAYMENTS_COLLECT, self::PAYMENTS_REFUND],
            'Invoices' => [self::INVOICES_VIEW, self::INVOICES_MANAGE],
            'Expenses' => [self::EXPENSES_VIEW, self::EXPENSES_MANAGE],
            'Attendance' => [self::ATTENDANCE_VIEW, self::ATTENDANCE_MARK],
            'Reports' => [self::REPORTS_VIEW],
            'Settings' => [self::SETTINGS_VIEW, self::SETTINGS_MANAGE],
            'Branches' => [self::BRANCHES_VIEW, self::BRANCHES_MANAGE],
            'Plans' => [self::PLANS_VIEW, self::PLANS_MANAGE],
            'Biometric' => [self::BIOMETRIC_VIEW, self::BIOMETRIC_MANAGE],
            'Communications' => [self::COMMUNICATIONS_VIEW, self::COMMUNICATIONS_MANAGE],
        ];
    }

    /** @return array<string, list<string>> */
    public static function defaultRoleMatrix(): array
    {
        $all = self::ALL;

        $branchManager = array_values(array_diff($all, [
            self::PERMISSIONS_MANAGE,
            self::SETTINGS_MANAGE,
            self::USERS_DELETE,
        ]));

        return [
            Roles::SUPER_ADMIN => $all,
            Roles::BRANCH_MANAGER => $branchManager,
            Roles::STAFF => [
                self::DASHBOARD_VIEW,
                self::STUDENTS_VIEW,
                self::STUDENTS_MANAGE,
                self::ADMISSIONS_VIEW,
                self::ADMISSIONS_MANAGE,
                self::ADMISSIONS_APPROVE,
                self::SUBSCRIPTIONS_VIEW,
                self::SUBSCRIPTIONS_MANAGE,
                self::PAYMENTS_VIEW,
                self::PAYMENTS_COLLECT,
                self::INVOICES_VIEW,
                self::INVOICES_MANAGE,
                self::ATTENDANCE_VIEW,
                self::ATTENDANCE_MARK,
                self::REPORTS_VIEW,
                self::PLANS_VIEW,
                self::COMMUNICATIONS_VIEW,
            ],
            Roles::RECEPTIONIST => [
                self::DASHBOARD_VIEW,
                self::STUDENTS_VIEW,
                self::ADMISSIONS_VIEW,
                self::ADMISSIONS_MANAGE,
                self::PAYMENTS_VIEW,
                self::PAYMENTS_COLLECT,
                self::ATTENDANCE_VIEW,
                self::ATTENDANCE_MARK,
            ],
            Roles::ATTENDANCE_OPERATOR => [
                self::DASHBOARD_VIEW,
                self::ATTENDANCE_VIEW,
                self::ATTENDANCE_MARK,
                self::STUDENTS_VIEW,
            ],
            Roles::STUDENT => [],
        ];
    }
}
