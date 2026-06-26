<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentAccountService
{
    private bool $lastCredentialsSent = false;

    public function __construct(
        private NotificationDispatchService $notifications,
    ) {}

    public function consumeLastCredentialsSent(): bool
    {
        $sent = $this->lastCredentialsSent;
        $this->lastCredentialsSent = false;

        return $sent;
    }

    public function ensureForStudent(Student $student): ?User
    {
        $provision = $this->provisionForStudent($student);

        if ($provision['password']) {
            $this->deliverPortalCredentials($student, $provision['password']);
        }

        return $provision['user'];
    }

    /**
     * @return array{user: ?User, password: ?string}
     */
    public function provisionForStudent(Student $student): array
    {
        if (! $student->email) {
            return ['user' => null, 'password' => null];
        }

        $student->loadMissing('user');

        if ($student->user_id && $student->user) {
            return ['user' => $student->user, 'password' => null];
        }

        $password = Str::password(10);
        $existing = User::where('email', $student->email)->first();

        if ($existing) {
            if ($existing->hasAnyRole(['super_admin', 'branch_manager', 'admin'])) {
                throw new \RuntimeException('This email belongs to a staff account. Use a different email for the student portal.');
            }

            $existing->update([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'name' => $student->name,
                'phone' => $student->phone ?? $existing->phone,
                'branch_id' => $student->branch_id ?? $existing->branch_id,
            ]);

            if (! $existing->hasRole('student')) {
                $existing->assignRole('student');
            }

            $student->update(['user_id' => $existing->id]);

            return ['user' => $existing->fresh(), 'password' => $password];
        }

        $user = $this->createPortalUser($student, $password);

        return ['user' => $user, 'password' => $password];
    }

    public function deliverPortalCredentials(Student $student, string $password): bool
    {
        return $this->sendPortalWelcome($student->fresh(), $password);
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

        $sent = $this->sendPortalWelcome($student->fresh(), $password, strict: true);

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
        $user->delete();

        return true;
    }

    private function sendPortalWelcome(Student $student, string $password, bool $strict = false): bool
    {
        try {
            $this->notifications->portalWelcome($student, $password);
            $this->lastCredentialsSent = true;

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->lastCredentialsSent = false;

            if ($strict) {
                throw $e;
            }

            return false;
        }
    }
}
