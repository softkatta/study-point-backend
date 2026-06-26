<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_SUPER_ADMIN_EMAIL', 'admin@studypoint.in');
        $name = env('SEED_SUPER_ADMIN_NAME', 'Super Admin');

        $admin = User::where('email', $email)->first();

        if ($admin) {
            $admin->update([
                'name' => $name,
                'status' => 'active',
            ]);
        } else {
            $admin = User::create([
                'email' => $email,
                'name' => $name,
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'demo1234'),
                'status' => 'active',
            ]);
        }

        $admin->syncRoles([\App\Support\Roles::SUPER_ADMIN]);
    }
}
