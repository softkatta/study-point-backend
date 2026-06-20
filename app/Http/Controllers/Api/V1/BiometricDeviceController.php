<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\AppSettingsService;
use App\Services\BiometricAccessService;
use App\Services\BiometricLogSyncService;
use App\Services\SmartOfficeService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BiometricDeviceController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private SmartOfficeService $smartOffice,
        private BiometricAccessService $biometricAccess,
        private BiometricLogSyncService $logSync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = BiometricDevice::with('branch')->orderBy('name');
        BranchScope::apply($query, $request->user(), 'branch_id');

        return ApiResponse::success([
            'devices' => $query->get(),
            'integration' => [
                'enabled' => (bool) ($this->settings->biometric()['enabled'] ?? false),
                'provider' => $this->settings->biometric()['provider'] ?? 'manual',
                'smartoffice_active' => $this->smartOffice->isActive(),
            ],
        ]);
    }

    public function show(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        return ApiResponse::success($device->load('branch'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'serial_number' => ['required', 'string', 'max:50', 'unique:biometric_devices,serial_number'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'type' => ['nullable', 'string', 'max:30'],
        ]);

        if ($branchId = BranchScope::branchId($request->user())) {
            $data['branch_id'] = $branchId;
        }

        if ($this->smartOffice->isActive()) {
            try {
                $this->smartOffice->addDevice($data['name'], $data['serial_number']);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        $device = BiometricDevice::create([
            ...$data,
            'type' => $data['type'] ?? 'fingerprint',
            'status' => 'active',
        ]);

        $enrollment = $this->biometricAccess->syncDeviceStudents($device);

        return ApiResponse::success([
            'device' => $device->load('branch'),
            'enrollment' => $enrollment,
        ], 'Device added and active students synced automatically.', 201);
    }

    public function update(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'serial_number' => ['sometimes', 'string', 'max:50', Rule::unique('biometric_devices', 'serial_number')->ignore($device->id)],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'type' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if ($branchId = BranchScope::branchId($request->user())) {
            $data['branch_id'] = $branchId;
        }

        $wasInactive = $device->status === 'inactive';
        $device->update($data);

        $enrollment = null;
        if ($wasInactive && ($device->fresh()->status ?? '') === 'active') {
            $enrollment = $this->biometricAccess->syncDeviceStudents($device->fresh());
        }

        return ApiResponse::success([
            'device' => $device->fresh('branch'),
            'enrollment' => $enrollment,
        ], 'Device updated');
    }

    public function destroy(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        if ($this->smartOffice->isActive()) {
            try {
                $this->smartOffice->deleteDevice($device->serial_number);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        $device->delete();

        return ApiResponse::success(null, 'Device deleted');
    }

    public function sync(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        $liveUsers = [];
        $enrollment = ['synced' => 0, 'skipped' => 0, 'errors' => 0];
        $logs = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0];

        if ($this->smartOffice->isActive()) {
            try {
                $liveUsers = $this->smartOffice->fetchLiveUsers($device->serial_number);
                $enrollment = $this->biometricAccess->syncDeviceStudents($device);
                $logs = $this->logSync->syncRecentLogs(60);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        }

        $device->update(['last_sync_at' => now()]);

        return ApiResponse::success([
            'device' => $device->fresh(),
            'live_users' => $liveUsers,
            'enrollment' => $enrollment,
            'attendance' => $logs,
        ], 'Device synced automatically');
    }

    public function enable(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        $device->update(['status' => 'active']);
        $enrollment = $this->biometricAccess->syncDeviceStudents($device->fresh());

        return ApiResponse::success([
            'device' => $device->fresh(),
            'enrollment' => $enrollment,
        ], 'Device enabled and students synced');
    }

    public function disable(Request $request, BiometricDevice $device): JsonResponse
    {
        $this->assertDeviceAccess($request, $device);

        $device->update(['status' => 'inactive']);

        return ApiResponse::success($device->fresh(), 'Device disabled');
    }

    private function assertDeviceAccess(Request $request, BiometricDevice $device): void
    {
        $branchId = BranchScope::branchId($request->user());
        if ($branchId !== null && (int) $device->branch_id !== $branchId) {
            abort(403, 'You can only manage devices for your branch.');
        }
    }
}
