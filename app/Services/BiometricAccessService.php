<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\BiometricDevice;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BiometricAccessService
{
    public function __construct(private SmartOfficeService $smartOffice) {}

    public function syncStudentAccess(Student $student): void
    {
        if (! $this->smartOffice->isActive()) {
            return;
        }

        $student->refresh();
        $code = SmartOfficeService::employeeCode($student->student_code ?? '');
        if ($code === '') {
            return;
        }

        $devices = $this->devicesForStudent($student);
        if ($devices->isEmpty()) {
            return;
        }

        if ($this->shouldGrantAccess($student)) {
            $this->grantAccess($student, $code, $devices);

            return;
        }

        $this->revokeAccess($student, $code, $devices);
    }

    /** @return array{synced: int, skipped: int, errors: int} */
    public function syncDeviceStudents(BiometricDevice $device): array
    {
        if (! $this->smartOffice->isActive() || $device->status !== 'active') {
            return ['synced' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $query = Student::query()->whereNotNull('student_code');
        if ($device->branch_id) {
            $query->where('branch_id', $device->branch_id);
        }

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($query->lazy() as $student) {
            if (! $this->shouldGrantAccess($student)) {
                $skipped++;

                continue;
            }

            try {
                $this->enrollStudentOnDevice($student, $device);
                $synced++;
            } catch (RuntimeException $e) {
                $errors++;
                Log::warning('SmartOffice: failed to sync student on device', [
                    'student_id' => $student->id,
                    'device_id' => $device->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return compact('synced', 'skipped', 'errors');
    }

    public function shouldGrantAccess(Student $student): bool
    {
        if ($student->status !== StudentStatus::Active) {
            return false;
        }

        if ($student->expiry && $student->expiry->toDateString() < now()->toDateString()) {
            return false;
        }

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function listBlockedStudents(?int $branchId = null): array
    {
        $query = Student::query()
            ->with('branch')
            ->whereNotNull('student_code');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('name')->get()
            ->filter(function (Student $student) {
                if ($this->shouldGrantAccess($student)) {
                    return false;
                }

                return $this->devicesForStudent($student)->isNotEmpty();
            })
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'employee_code' => SmartOfficeService::employeeCode($student->student_code ?? ''),
                'name' => $student->name,
                'branch_id' => $student->branch_id,
                'branch' => $student->branch?->name,
                'status' => $student->status->value,
                'reason' => $this->blockReason($student),
                'reason_label' => $this->blockReasonLabel($student),
                'expiry' => $student->expiry?->format('Y-m-d'),
                'devices' => $this->devicesForStudent($student)->map(fn (BiometricDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'serial_number' => $device->serial_number,
                ])->values()->all(),
                'machine_status' => 'blocked',
            ])
            ->values()
            ->all();
    }

    private function blockReason(Student $student): string
    {
        if ($student->status === StudentStatus::Blacklisted) {
            return 'blocked';
        }

        if ($student->status === StudentStatus::Expired) {
            return 'expired';
        }

        if ($student->expiry && $student->expiry->toDateString() < now()->toDateString()) {
            return 'expired';
        }

        return 'inactive';
    }

    private function blockReasonLabel(Student $student): string
    {
        return match ($this->blockReason($student)) {
            'blocked' => 'Admin blocked',
            'expired' => 'Membership expired',
            'inactive' => 'Inactive student',
            default => 'Access denied',
        };
    }

    public function enrollStudentOnDevice(Student $student, BiometricDevice $device): void
    {
        $code = SmartOfficeService::employeeCode($student->student_code ?? '');
        if ($code === '') {
            throw new RuntimeException('Student code is required for biometric enrollment.');
        }

        $expiry = $student->expiry?->format('Y-m-d') ?? now()->addYear()->format('Y-m-d');
        $isFace = in_array($device->type, ['face', 'fingerprint_face'], true);
        $isFp = in_array($device->type, ['fingerprint', 'fingerprint_face'], true);

        $this->safeAddEmployee($student, $code);

        $this->smartOffice->uploadUser([
            'employee_name' => $student->name,
            'employee_code' => $code,
            'serial_number' => $device->serial_number,
            'is_fp_upload' => $isFp,
            'is_face_upload' => $isFace,
        ]);

        $this->smartOffice->setUserExpiration($code, $expiry, $device->serial_number);
        $this->smartOffice->blockUser($code, $device->serial_number, true);

        $cacheKey = "biometric_enroll_triggered:{$student->id}:{$device->id}";
        if (! Cache::get($cacheKey)) {
            try {
                $this->smartOffice->triggerOnlineEnrollment(
                    $device->serial_number,
                    $code,
                    $student->name,
                );
                Cache::forever($cacheKey, true);
            } catch (RuntimeException $e) {
                Log::info('SmartOffice: online enrollment trigger skipped', [
                    'student_id' => $student->id,
                    'device_id' => $device->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @param Collection<int, BiometricDevice> $devices */
    private function grantAccess(Student $student, string $code, Collection $devices): void
    {
        foreach ($devices as $device) {
            try {
                $this->enrollStudentOnDevice($student, $device);
            } catch (RuntimeException $e) {
                Log::warning('SmartOffice: failed to grant biometric access', [
                    'student_id' => $student->id,
                    'student_code' => $student->student_code,
                    'device_id' => $device->id,
                    'serial_number' => $device->serial_number,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @param Collection<int, BiometricDevice> $devices */
    private function revokeAccess(Student $student, string $code, Collection $devices): void
    {
        foreach ($devices as $device) {
            try {
                $this->smartOffice->blockUser($code, $device->serial_number, false);
            } catch (RuntimeException $e) {
                Log::warning('SmartOffice: failed to revoke biometric access', [
                    'student_id' => $student->id,
                    'student_code' => $student->student_code,
                    'device_id' => $device->id,
                    'serial_number' => $device->serial_number,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function safeAddEmployee(Student $student, string $code): void
    {
        try {
            $this->smartOffice->addEmployee([
                'code' => $code,
                'name' => $student->name,
                'doj' => $student->valid_from?->format('Y-m-d') ?? now()->format('Y-m-d'),
            ]);
        } catch (RuntimeException $e) {
            if (! $this->isAlreadyExistsError($e)) {
                throw $e;
            }
        }
    }

    private function isAlreadyExistsError(RuntimeException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'exist')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'already');
    }

    /** @return Collection<int, BiometricDevice> */
    private function devicesForStudent(Student $student): Collection
    {
        return BiometricDevice::query()
            ->where('status', 'active')
            ->where(function ($query) use ($student) {
                $query->whereNull('branch_id');
                if ($student->branch_id) {
                    $query->orWhere('branch_id', $student->branch_id);
                }
            })
            ->get();
    }
}
