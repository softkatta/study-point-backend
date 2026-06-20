<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $branch = Branch::query()->first();

        $manager = User::updateOrCreate(
            ['email' => 'manager@studypoint.in'],
            [
                'name' => 'Branch Manager',
                'phone' => '+91 99000 00001',
                'branch_id' => $branch?->id,
                'status' => 'active',
                'password' => 'demo1234',
            ]
        );
        $manager->syncRoles(['branch_manager']);

        $studentUser = User::updateOrCreate(
            ['email' => 'student@studypoint.in'],
            [
                'name' => 'Demo Student',
                'phone' => '+91 99000 00002',
                'branch_id' => $branch?->id,
                'status' => 'active',
                'password' => 'demo1234',
            ]
        );
        $studentUser->syncRoles(['student']);

        Student::updateOrCreate(
            ['email' => 'student@studypoint.in'],
            [
                'user_id' => $studentUser->id,
                'student_code' => 'SP2024001',
                'verify_token' => strtoupper(substr(md5('student@studypoint.in'), 0, 12)),
                'qr_token' => strtoupper(substr(md5('student-qr@studypoint.in'), 0, 12)),
                'name' => 'Demo Student',
                'phone' => '+91 99000 00002',
                'branch_id' => $branch?->id,
                'status' => 'active',
                'valid_from' => now()->toDateString(),
                'expiry' => now()->addMonths(1)->toDateString(),
            ]
        );
    }
}
