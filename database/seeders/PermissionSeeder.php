<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** @var array<string, array{label: string, is_system: bool, requires_branch: bool}> */
    private const ROLE_META = [
        Roles::SUPER_ADMIN => ['label' => 'Super Admin', 'is_system' => true, 'requires_branch' => false],
        Roles::BRANCH_MANAGER => ['label' => 'Branch Manager', 'is_system' => true, 'requires_branch' => true],
        Roles::STAFF => ['label' => 'Staff', 'is_system' => true, 'requires_branch' => true],
        Roles::RECEPTIONIST => ['label' => 'Receptionist', 'is_system' => true, 'requires_branch' => true],
        Roles::ATTENDANCE_OPERATOR => ['label' => 'Attendance Operator', 'is_system' => true, 'requires_branch' => true],
        Roles::STUDENT => ['label' => 'Student', 'is_system' => true, 'requires_branch' => false],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permissions::ALL as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $matrix = Permissions::defaultRoleMatrix();

        foreach (Roles::MANAGEABLE as $roleName) {
            $meta = self::ROLE_META[$roleName];
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                $meta
            );
            $role->update($meta);
            $role->syncPermissions($matrix[$roleName] ?? []);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
