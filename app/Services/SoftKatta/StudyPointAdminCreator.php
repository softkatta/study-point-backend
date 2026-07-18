<?php

namespace App\Services\SoftKatta;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Support\Facades\Hash;
use SoftKatta\Licensing\Contracts\CreatesAdminUser;
use Spatie\Permission\PermissionRegistrar;

class StudyPointAdminCreator implements CreatesAdminUser
{
    public function create(array $data): object
    {
        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'password_changed_at' => now(),
            ],
        );

        if (class_exists(Role::class)) {
            $role = Role::findOrCreate(Roles::SUPER_ADMIN, 'web');
            $all = Permissions::ALL;
            if ($all !== []) {
                $role->syncPermissions($all);
            }
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([Roles::SUPER_ADMIN]);
            } elseif (method_exists($user, 'assignRole')) {
                $user->assignRole(Roles::SUPER_ADMIN);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $user;
    }
}
