<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BiometricLogSyncService
{
    public function __construct(
        private SmartOfficeService $smartOffice,
        private AttendanceService $attendance,
    ) {}

    /** @return array{imported: int, skipped: int, errors: int, total: int} */
    public function syncRecentLogs(int $minutes = 15): array
    {
        if (! $this->smartOffice->isActive()) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0];
        }

        $from = now()->subMinutes($minutes)->format('Y-m-d H:i:s');
        $to = now()->format('Y-m-d H:i:s');

        try {
            $logs = $this->smartOffice->getDeviceLogs($from, $to);
        } catch (RuntimeException $e) {
            Log::warning('SmartOffice: failed to fetch device logs', ['message' => $e->getMessage()]);

            return ['imported' => 0, 'skipped' => 0, 'errors' => 1, 'total' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($logs as $log) {
            if (! is_array($log)) {
                $skipped++;

                continue;
            }

            try {
                $result = $this->importLogEntry($log);
                if ($result === 'imported') {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (RuntimeException $e) {
                $errors++;
                Log::debug('SmartOffice: skipped log entry', ['message' => $e->getMessage(), 'log' => $log]);
            }
        }

        Setting::saveSection('biometric', ['last_log_sync_at' => now()->toIso8601String()]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($logs),
        ];
    }

    /** @param array<string, mixed> $log */
    private function importLogEntry(array $log): string
    {
        $employeeCode = trim((string) ($log['EmployeeCode'] ?? $log['employee_code'] ?? ''));
        if ($employeeCode === '') {
            return 'skipped';
        }

        $student = Student::findByEmployeeCode($employeeCode);
        if (! $student) {
            return 'skipped';
        }

        $rawTime = $log['LogDate'] ?? $log['PunchTime'] ?? $log['DateTime'] ?? $log['log_date'] ?? null;
        if (! $rawTime) {
            return 'skipped';
        }

        $at = Carbon::parse((string) $rawTime);
        $direction = strtolower(trim((string) ($log['PunchDirection'] ?? $log['Direction'] ?? $log['punch_direction'] ?? 'in')));
        $isCheckOut = str_contains($direction, 'out');

        $record = $this->attendance->recordBiometricPunch($student, $at, $isCheckOut);

        return $record ? 'imported' : 'skipped';
    }
}
