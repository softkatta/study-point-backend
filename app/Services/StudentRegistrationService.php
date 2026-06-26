<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class StudentRegistrationService
{
    public function __construct(
        private SecurityPolicyService $security,
        private AuditService $audit,
    ) {}

    public function isAllowed(): bool
    {
        return (bool) ($this->security->config()['allow_student_self_register'] ?? false);
    }

    public function register(array $data, ?string $ip = null): array
    {
        if (! $this->isAllowed()) {
            throw new RuntimeException('Student self-registration is disabled.');
        }

        if (User::where('email', $data['email'])->exists()) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $studentCode = $this->nextStudentCode();
        $verifyToken = Str::random(32);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'branch_id' => $data['branch_id'] ?? null,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $user->assignRole('student');

        $student = Student::create([
            'user_id' => $user->id,
            'student_code' => $studentCode,
            'verify_token' => $verifyToken,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'branch_id' => $data['branch_id'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => 'active',
        ]);

        $this->audit->log('student.self_register', $user, 'student', (string) $student->id, null, [
            'student_code' => $studentCode,
            'ip' => $ip,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->load(['branch', 'roles']),
            'student_code' => $studentCode,
        ];
    }

    private function nextStudentCode(): string
    {
        $last = Student::orderByDesc('id')->value('student_code');
        $num = $last ? (int) preg_replace('/\D/', '', $last) + 1 : 2025001;

        return 'SP'.$num;
    }
}
