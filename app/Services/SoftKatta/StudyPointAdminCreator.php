<?php

namespace App\Services\SoftKatta;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use SoftKatta\Licensing\Contracts\CreatesAdminUser;
use Spatie\Permission\Models\Role;

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
            Role::findOrCreate('super_admin', 'web');
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('super_admin');
            }
        }

        return $user;
    }
}
