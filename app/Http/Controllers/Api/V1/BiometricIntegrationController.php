<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\Student;
use App\Services\BiometricAccessService;
use App\Services\SmartOfficeService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricIntegrationController extends Controller
{
    public function __construct(
        private SmartOfficeService $smartOffice,
        private BiometricAccessService $biometricAccess,
    ) {}

    public function logs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'string', 'max:30'],
            'to_date' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::success(['logs' => []]);
        }

        $from = $validated['from_date'] ?? now()->subDays(7)->format('Y-m-d H:i:s');
        $to = $validated['to_date'] ?? now()->format('Y-m-d H:i:s');

        return ApiResponse::success([
            'logs' => $this->smartOffice->getDeviceLogs($from, $to),
        ]);
    }

    public function blockedStudents(Request $request): JsonResponse
    {
        $branchId = BranchScope::branchId($request->user());

        $students = $this->biometricAccess->listBlockedStudents($branchId);

        return ApiResponse::success([
            'students' => $students,
            'total' => count($students),
            'smartoffice_active' => $this->smartOffice->isActive(),
        ]);
    }

    public function deviceCommands(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::success(['records' => [], 'result' => true]);
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'string', 'max:30'],
            'to_date' => ['nullable', 'string', 'max:30'],
        ]);

        $from = $validated['from_date'] ?? now()->subDay()->format('Y-m-d H:i');
        $to = $validated['to_date'] ?? now()->format('Y-m-d H:i');

        return ApiResponse::success(
            $this->smartOffice->getDeviceCommands($from, $to, $device->serial_number)
        );
    }

    public function enrollStudent(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:biometric_devices,id'],
        ]);

        $device = BiometricDevice::query()->findOrFail($validated['device_id']);
        $this->assertDeviceAccess($request, $device);
        $this->assertStudentBranch($request, $student);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::error('Enable SmartOffice in Settings → Biometric API.', 422);
        }

        $this->biometricAccess->enrollStudentOnDevice($student, $device);

        return ApiResponse::success(null, 'Student enrolled on biometric device.');
    }

    public function triggerFingerprint(Request $request, Student $student): JsonResponse
    {
        $device = $this->resolveDevice($request);
        $this->assertStudentBranch($request, $student);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::error('Enable SmartOffice in Settings → Biometric API.', 422);
        }

        $code = SmartOfficeService::employeeCode($student->student_code);
        $result = $this->smartOffice->triggerOnlineEnrollment(
            $device->serial_number,
            $code,
            $student->name,
        );

        return ApiResponse::success($result, 'Fingerprint enrollment triggered.');
    }

    public function uploadFace(Request $request, Student $student): JsonResponse
    {
        $device = $this->resolveDevice($request);
        $this->assertStudentBranch($request, $student);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::error('Enable SmartOffice in Settings → Biometric API.', 422);
        }

        $code = SmartOfficeService::employeeCode($student->student_code);
        $result = $this->smartOffice->uploadUser([
            'employee_name' => $student->name,
            'employee_code' => $code,
            'serial_number' => $device->serial_number,
            'is_face_upload' => true,
            'is_fp_upload' => false,
        ]);

        return ApiResponse::success($result, 'Face upload command sent.');
    }

    public function blockStudent(Request $request, Student $student): JsonResponse
    {
        $device = $this->resolveDevice($request);
        $this->assertStudentBranch($request, $student);

        if (! $this->smartOffice->isActive()) {
            return ApiResponse::error('SmartOffice is not active. Enable it in Settings → Biometric API.', 422);
        }

        $code = SmartOfficeService::employeeCode($student->student_code);
        $result = $this->smartOffice->blockUser($code, $device->serial_number, false);

        return ApiResponse::success($result, 'Student blocked on biometric device.');
    }

    private function resolveDevice(Request $request): BiometricDevice
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:biometric_devices,id'],
        ]);

        $device = BiometricDevice::query()->findOrFail($validated['device_id']);
        $this->assertDeviceAccess($request, $device);

        return $device;
    }

    private function assertDeviceAccess(Request $request, BiometricDevice $device): void
    {
        $branchId = BranchScope::branchId($request->user());
        if ($branchId !== null && (int) $device->branch_id !== $branchId) {
            abort(403, 'You can only manage devices for your branch.');
        }
    }

    private function assertStudentBranch(Request $request, Student $student): void
    {
        $branchId = BranchScope::branchId($request->user());
        if ($branchId !== null && (int) $student->branch_id !== $branchId) {
            abort(403, 'You can only manage students for your branch.');
        }
    }
}
