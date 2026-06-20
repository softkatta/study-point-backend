<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceDefaults;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class AttendanceService
{
    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave'];

    public function normalizeStudentQrToken(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~/student/verify/([^/?#\s]+)~', $raw, $matches)) {
            return urldecode($matches[1]);
        }

        return $raw;
    }

    public function normalizeBranchQrToken(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~/attendance/branch/([^/?#\s]+)~', $raw, $matches)) {
            return urldecode($matches[1]);
        }

        return $raw;
    }

    public function findStudentByToken(string $token): ?Student
    {
        $token = $this->normalizeStudentQrToken($token);

        return Student::query()
            ->with(['branch', 'subscriptions'])
            ->where(function ($q) use ($token) {
                $q->where('qr_token', $token)
                    ->orWhere('verify_token', $token)
                    ->orWhere('student_code', $token);
            })
            ->first();
    }

    public function findBranchByAttendanceToken(string $token): ?Branch
    {
        $token = $this->normalizeBranchQrToken($token);

        return Branch::query()->where('attendance_qr_token', $token)->first();
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return AttendanceDefaults::merge(Setting::getSection('attendance'));
    }

    public function resolveStatus(Carbon $at): string
    {
        $cfg = $this->settings();
        $time = $at->format('H:i');
        $lateAfter = substr((string) ($cfg['late_after'] ?? '09:15'), 0, 5);
        $halfDayAfter = substr((string) ($cfg['half_day_after'] ?? '12:00'), 0, 5);

        if ($time > $halfDayAfter) {
            return 'half_day';
        }
        if ($time > $lateAfter) {
            return 'late';
        }

        return 'present';
    }

    /** @return array{student: Student, subscription_active: bool} */
    public function validateStudentForAttendance(Student $student): array
    {
        if ($student->status === StudentStatus::Blacklisted) {
            throw new RuntimeException('Student is blocked. Attendance cannot be marked.');
        }

        if ($student->status === StudentStatus::Expired) {
            throw new RuntimeException('Student membership expired. Renew before attendance.');
        }

        if ($student->status !== StudentStatus::Active) {
            throw new RuntimeException('Inactive student. Attendance cannot be marked.');
        }

        if ($student->expiry && $student->expiry->toDateString() < now()->toDateString()) {
            throw new RuntimeException('Admission expired. Student must renew before attendance.');
        }

        $branch = $student->branch;
        if (! $branch || ($branch->status ?? 'active') !== 'active') {
            throw new RuntimeException('Branch is not active.');
        }

        $plan = $student->plan_id ? Plan::find($student->plan_id) : null;
        if ($plan && ($plan->status ?? 'active') !== 'active') {
            throw new RuntimeException('Course / plan is not active.');
        }

        $subscription = $student->subscriptions()->where('status', 'active')->latest()->first();
        if ($subscription && $subscription->end_date && $subscription->end_date->toDateString() < now()->toDateString()) {
            throw new RuntimeException('Subscription expired. Renew before attendance.');
        }

        return ['student' => $student, 'subscription_active' => (bool) $subscription];
    }

    public function todayLog(Student $student, ?string $date = null): ?AttendanceLog
    {
        $date = $date ?: now()->toDateString();

        return AttendanceLog::query()
            ->where('student_id', $student->id)
            ->where(function ($q) use ($date) {
                $q->whereDate('attendance_date', $date)
                    ->orWhereDate('check_in', $date);
            })
            ->latest('check_in')
            ->first();
    }

    /**
     * Toggle check-in / check-out for a student (same QR scan for both).
     *
     * @return array<string, mixed>
     */
    public function processStudentAttendance(
        Student $student,
        ?User $marker,
        string $source,
        ?int $requiredBranchId = null,
    ): array {
        $this->validateStudentForAttendance($student);

        if ($requiredBranchId !== null && (int) $student->branch_id !== (int) $requiredBranchId) {
            throw new RuntimeException('You are not enrolled at this branch. Scan QR at your branch only.');
        }

        $today = now()->toDateString();
        $existing = $this->todayLog($student, $today);
        $role = $marker?->roles->first()?->name ?? ($source === 'branch_qr' ? 'student' : 'staff');

        if (! $existing) {
            $now = now();
            $log = AttendanceLog::create([
                'student_id' => $student->id,
                'branch_id' => $student->branch_id,
                'attendance_date' => $today,
                'check_in' => $now,
                'status' => $this->resolveStatus($now),
                'source' => $source,
                'marked_by_user_id' => $marker?->id,
                'marked_by_role' => $role,
            ]);

            return $this->formatScanResult($log, $student, 'check_in', 'Check-in recorded successfully.');
        }

        if (! $existing->check_out) {
            $existing->update([
                'check_out' => now(),
                'hours' => round($existing->check_in->diffInMinutes(now()) / 60, 2),
            ]);
            $log = $existing->fresh()->load(['student.branch', 'markedBy']);

            return $this->formatScanResult($log, $student, 'check_out', 'Check-out recorded successfully.');
        }

        throw new RuntimeException('Already checked in and out for today.');
    }

    /** @return array<string, mixed> */
    public function scanStudentQr(string $rawToken, User $marker): array
    {
        $token = $this->normalizeStudentQrToken($rawToken);
        if ($token === '') {
            throw new RuntimeException('Invalid QR code.');
        }

        $student = $this->findStudentByToken($token);
        if (! $student) {
            throw new RuntimeException('Invalid QR. Student not found.');
        }

        return $this->processStudentAttendance($student, $marker, 'qr');
    }

    /** @return array<string, mixed> */
    public function scanBranchQr(string $rawToken, User $studentUser): array
    {
        $token = $this->normalizeBranchQrToken($rawToken);
        if ($token === '') {
            throw new RuntimeException('Invalid branch QR code.');
        }

        $branch = $this->findBranchByAttendanceToken($token);
        if (! $branch) {
            throw new RuntimeException('Invalid branch QR. Branch not found.');
        }

        if (($branch->status ?? 'active') !== 'active') {
            throw new RuntimeException('Branch is not active.');
        }

        $student = Student::query()
            ->with(['branch', 'subscriptions'])
            ->where('user_id', $studentUser->id)
            ->first();

        if (! $student) {
            throw new RuntimeException('Student profile not found.');
        }

        return $this->processStudentAttendance($student, $studentUser, 'branch_qr', (int) $branch->id);
    }

    /** @return array<string, mixed> */
    protected function formatScanResult(AttendanceLog $log, Student $student, string $action, string $message): array
    {
        $student->loadMissing('branch');

        return [
            'action' => $action,
            'already_marked' => false,
            'message' => $message,
            'log' => $log->loadMissing(['student.branch', 'markedBy']),
            'student' => $student,
            'status' => $log->status,
            'time' => $action === 'check_out'
                ? $log->check_out?->toIso8601String()
                : $log->check_in?->toIso8601String(),
        ];
    }

    public function checkIn(Student $student, string $source = 'biometric', ?User $marker = null, ?string $status = null): AttendanceLog
    {
        $existing = $this->todayLog($student);
        if ($existing) {
            return $existing;
        }

        $now = now();
        $role = $marker?->roles->first()?->name;

        return AttendanceLog::create([
            'student_id' => $student->id,
            'branch_id' => $student->branch_id,
            'attendance_date' => $now->toDateString(),
            'check_in' => $now,
            'status' => $status ?? $this->resolveStatus($now),
            'source' => $source,
            'marked_by_user_id' => $marker?->id,
            'marked_by_role' => $role,
        ]);
    }

    public function checkOut(Student $student): ?AttendanceLog
    {
        $log = $this->todayLog($student);
        if (! $log || $log->check_out) {
            return null;
        }

        $log->update([
            'check_out' => now(),
            'hours' => round($log->check_in->diffInMinutes(now()) / 60, 2),
        ]);

        return $log->fresh();
    }

    public function recordBiometricPunch(Student $student, Carbon $at, bool $isCheckOut): ?AttendanceLog
    {
        try {
            $this->validateStudentForAttendance($student);
        } catch (RuntimeException) {
            return null;
        }

        $date = $at->toDateString();
        $existing = $this->todayLog($student, $date);

        if ($isCheckOut) {
            if (! $existing || $existing->check_out) {
                return null;
            }

            if ($existing->check_out && $existing->check_out->equalTo($at)) {
                return null;
            }

            $existing->update([
                'check_out' => $at,
                'hours' => round($existing->check_in->diffInMinutes($at) / 60, 2),
            ]);

            return $existing->fresh();
        }

        if ($existing) {
            if ($existing->check_in->equalTo($at)) {
                return null;
            }

            return null;
        }

        return AttendanceLog::create([
            'student_id' => $student->id,
            'branch_id' => $student->branch_id,
            'attendance_date' => $date,
            'check_in' => $at,
            'status' => $this->resolveStatus($at),
            'source' => 'biometric',
            'marked_by_role' => 'system',
        ]);
    }

    public static function generateQrToken(): string
    {
        return strtoupper(Str::random(10));
    }

    public static function generateBranchAttendanceToken(): string
    {
        return strtoupper(Str::random(12));
    }

    public function branchAttendanceQrUrl(Branch $branch): string
    {
        $token = $branch->attendance_qr_token;
        if (! $token) {
            $token = self::generateBranchAttendanceToken();
            $branch->update(['attendance_qr_token' => $token]);
        }

        $base = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return "{$base}/attendance/branch/{$token}";
    }

    public function ensureBranchAttendanceToken(Branch $branch): Branch
    {
        if (! $branch->attendance_qr_token) {
            $branch->update(['attendance_qr_token' => self::generateBranchAttendanceToken()]);
            $branch->refresh();
        }

        return $branch;
    }

    public function regenerateBranchAttendanceToken(Branch $branch): Branch
    {
        $branch->update(['attendance_qr_token' => self::generateBranchAttendanceToken()]);

        return $branch->fresh();
    }

    /** @return array<string, mixed> */
    public function dailySummary(?int $branchId, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $studentsQuery = Student::query()->where('status', 'active');
        if ($branchId) {
            $studentsQuery->where('branch_id', $branchId);
        }
        $activeStudents = (clone $studentsQuery)->count();

        $logsQuery = AttendanceLog::query()->where(function ($q) use ($date) {
            $q->whereDate('attendance_date', $date)->orWhereDate('check_in', $date);
        });
        if ($branchId) {
            $logsQuery->where('branch_id', $branchId);
        }

        $logs = (clone $logsQuery)->get();
        $presentToday = $logs->pluck('student_id')->unique()->count();
        $avgHours = (float) $logs->whereNotNull('hours')->avg('hours');
        $qrScansToday = (clone $logsQuery)->whereIn('source', ['qr', 'branch_qr'])->count();
        $lateToday = $logs->where('status', 'late')->count();

        $hourly = [];
        foreach ($logs as $log) {
            if (! $log->check_in) {
                continue;
            }
            $hour = Carbon::parse($log->check_in)->format('gA');
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
        }

        $peakHour = '';
        $peakCount = 0;
        foreach ($hourly as $hour => $count) {
            if ($count > $peakCount) {
                $peakCount = $count;
                $peakHour = $hour;
            }
        }

        return [
            'date' => $date,
            'active_students' => $activeStudents,
            'present_today' => $presentToday,
            'absent_today' => max(0, $activeStudents - $presentToday),
            'late_today' => $lateToday,
            'avg_hours' => round($avgHours, 1),
            'peak_hour' => $peakHour ?: null,
            'qr_scans_today' => $qrScansToday,
            'hourly_checkins' => collect($hourly)->map(fn ($count, $hour) => ['hour' => $hour, 'count' => $count])->values()->all(),
        ];
    }
}
