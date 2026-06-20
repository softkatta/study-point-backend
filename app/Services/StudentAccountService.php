<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentAccountService
{
    public function __construct(
        private NotificationDispatchService $notifications,
    ) {}

    public function ensureForStudent(Student $student): ?User
    {
        if (! $student->email) {
            return null;
        }

        $student->loadMissing('user');

        if ($student->user_id && $student->user) {
            return $student->user;
        }

        $existing = User::where('email', $student->email)->first();
        if ($existing) {
            $student->update(['user_id' => $existing->id]);
            if (! $existing->hasRole('student')) {
                $existing->assignRole('student');
            }

            return $existing;
        }

        $password = Str::password(10);
        $user = $this->createPortalUser($student, $password);
        $this->deferPortalWelcome($student->id, $password);

        return $user;
    }

    public function resendPortalCredentials(Student $student): array
    {
        if (! $student->email) {
            throw new \RuntimeException('Student email is required to send portal credentials.');
        }

        $student->loadMissing('user');
        $password = Str::password(10);

        if ($student->user) {
            $student->user->update([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
            ]);
        } else {
            $existing = User::where('email', $student->email)->first();
            if ($existing) {
                $existing->update([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                ]);
                if (! $existing->hasRole('student')) {
                    $existing->assignRole('student');
                }
                $student->update(['user_id' => $existing->id]);
            } else {
                $this->createPortalUser($student, $password);
            }
        }

        $sent = $this->sendPortalWelcome($student->fresh(), $password);

        return [
            'email' => $student->email,
            'phone' => $student->phone,
            'portal_ready' => true,
            'credentials_sent' => $sent,
        ];
    }

    private function createPortalUser(Student $student, string $password): User
    {
        $user = User::create([
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'password' => Hash::make($password),
            'branch_id' => $student->branch_id,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $user->assignRole('student');
        $student->update(['user_id' => $user->id]);

        return $user;
    }

    public function deletePortalUser(Student $student): bool
    {
        $student->loadMissing('user');
        $user = $student->user;

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'branch_manager', 'admin'])) {
            return false;
        }

        if (Student::where('user_id', $user->id)->where('id', '!=', $student->id)->exists()) {
            return false;
        }

        $user->tokens()->delete();
        $user->forceDelete();

        return true;
    }

    private function deferPortalWelcome(int $studentId, string $password): void
    {
        dispatch(function () use ($studentId, $password) {
            $student = Student::find($studentId);
            if (! $student) {
                return;
            }

            $this->sendPortalWelcome($student, $password);
        })->afterResponse();
    }

    private function sendPortalWelcome(Student $student, string $password): bool
    {
        try {
            $this->notifications->portalWelcome($student, $password);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
