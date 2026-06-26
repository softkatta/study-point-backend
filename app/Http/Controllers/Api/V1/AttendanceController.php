<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Controllers\Controller;

use App\Http\Resources\AttendanceLogResource;

use App\Models\AttendanceLog;

use App\Models\Student;

use App\Models\User;

use App\Services\AttendanceService;
use App\Services\WhatsAppDispatchService;

use App\Support\ApiResponse;

use App\Support\AttendanceGate;

use App\Support\BranchScope;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class AttendanceController extends Controller

{

    public function __construct(
        private AttendanceService $attendance,
        private WhatsAppDispatchService $whatsappDispatch,
    ) {}



    public function index(Request $request): JsonResponse

    {

        $query = AttendanceLog::with(['student', 'branch'])->latest('check_in');



        if ($branchId = BranchScope::branchId($request->user())) {

            $query->where('branch_id', $branchId);

        }

        if ($request->filled('student_id')) {

            $query->where('student_id', $request->student_id);

        }

        if ($request->filled('date')) {

            $query->whereDate('check_in', $request->date);

        }

        if ($request->filled('source')) {

            $query->where('source', $request->source);

        }



        return ApiResponse::success(

            AttendanceLogResource::collection($query->paginate($request->integer('per_page', 50)))

        );

    }



    public function summary(Request $request): JsonResponse

    {

        $branchId = BranchScope::branchId($request->user());



        return ApiResponse::success(

            $this->attendance->dailySummary($branchId, $request->input('date'))

        );

    }



    public function store(Request $request): JsonResponse

    {

        AttendanceGate::ensure($request->user());

        $data = $request->validate([

            'student_id' => ['required', 'exists:students,id'],

            'check_in' => ['nullable', 'date'],

            'check_out' => ['nullable'],

            'status' => ['nullable', 'string', 'max:20'],

            'source' => ['nullable', 'string', 'max:20'],

        ]);



        $student = Student::findOrFail($data['student_id']);

        AttendanceGate::ensure($request->user(), $student);

        if ($branchId = BranchScope::branchId($request->user())) {

            if ((int) $student->branch_id !== $branchId) {

                return ApiResponse::error('Student does not belong to your branch.', 403);

            }

        }



        if (! empty($data['check_out']) && is_string($data['check_out']) && ! empty($data['check_in'])) {

            $checkIn = now()->parse($data['check_in']);

            $checkOut = now()->parse($data['check_out']);

            $hours = round($checkIn->diffInMinutes($checkOut) / 60, 2);



            $log = AttendanceLog::create([

                'student_id' => $student->id,

                'branch_id' => $student->branch_id,

                'check_in' => $checkIn,

                'check_out' => $checkOut,

                'hours' => $hours,

                'status' => $data['status'] ?? 'present',

                'source' => $data['source'] ?? 'manual',

            ]);

        } elseif (! empty($data['check_out'])) {

            $log = $this->attendance->checkOut($student);

            if (! $log) {

                return ApiResponse::error('No open check-in found for this student.', 422);

            }

        } else {

            $source = $data['source'] ?? 'manual';

            if (! empty($data['check_in'])) {

                $log = AttendanceLog::create([

                    'student_id' => $student->id,

                    'branch_id' => $student->branch_id,

                    'check_in' => now()->parse($data['check_in']),

                    'status' => $data['status'] ?? 'present',

                    'source' => $source,

                ]);

            } else {

                $log = $this->attendance->checkIn($student, $source, $request->user());

            }

        }



        try {
            $this->whatsappDispatch->queueAttendanceAlert($student, $log);
        } catch (\Throwable $e) {
            report($e);
        }

        return ApiResponse::success(new AttendanceLogResource($log->load(['student', 'branch'])), 'Attendance marked', 201);

    }



    public function checkOut(Request $request, AttendanceLog $attendanceLog): JsonResponse

    {

        AttendanceGate::ensure($request->user());

        if ($branchId = BranchScope::branchId($request->user())) {

            if ((int) $attendanceLog->branch_id !== $branchId) {

                return ApiResponse::error('Not allowed for this branch.', 403);

            }

        }



        if ($attendanceLog->check_out) {

            return ApiResponse::error('Already checked out.', 422);

        }



        $attendanceLog->update([

            'check_out' => now(),

            'hours' => round($attendanceLog->check_in->diffInMinutes(now()) / 60, 2),

        ]);



        try {
            $this->whatsappDispatch->queueAttendanceAlert($attendanceLog->student, $attendanceLog);
        } catch (\Throwable $e) {
            report($e);
        }

        return ApiResponse::success(new AttendanceLogResource($attendanceLog->fresh()->load(['student', 'branch'])), 'Check-out recorded');

    }



    public function export(Request $request): JsonResponse

    {

        $query = AttendanceLog::with(['student', 'branch'])->latest('check_in');



        if ($branchId = BranchScope::branchId($request->user())) {

            $query->where('branch_id', $branchId);

        }

        if ($request->filled('date')) {

            $query->whereDate('check_in', $request->date);

        }

        if ($request->filled('source')) {

            $query->where('source', $request->source);

        }



        $logs = $query->limit(500)->get();



        return ApiResponse::success(AttendanceLogResource::collection($logs));

    }

    public function scan(Request $request): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        $data = $request->validate([
            'qr' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $this->attendance->scanStudentQr($data['qr'], $request->user());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        try {
            $this->whatsappDispatch->queueAttendanceAlert($result['log']->student, $result['log']);
        } catch (\Throwable $e) {
            report($e);
        }

        return ApiResponse::success($this->scanPayload($result, $request->user()), $result['message'], 201);
    }

    public function branchQrCodes(Request $request): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        $query = \App\Models\Branch::query()->orderBy('name');
        if ($branchId = BranchScope::branchId($request->user())) {
            $query->where('id', $branchId);
        }

        $items = $query->get()->map(function ($branch) {
            $branch = $this->attendance->ensureBranchAttendanceToken($branch);

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->city,
                'status' => $branch->status,
                'token' => $branch->attendance_qr_token,
                'qr_url' => $this->attendance->branchAttendanceQrUrl($branch),
            ];
        });

        return ApiResponse::success($items);
    }

    public function regenerateBranchQr(Request $request, \App\Models\Branch $branch): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        if ($branchId = BranchScope::branchId($request->user())) {
            if ((int) $branch->id !== $branchId) {
                return ApiResponse::error('Not allowed for this branch.', 403);
            }
        }

        $branch = $this->attendance->regenerateBranchAttendanceToken($branch);

        return ApiResponse::success([
            'id' => $branch->id,
            'name' => $branch->name,
            'token' => $branch->attendance_qr_token,
            'qr_url' => $this->attendance->branchAttendanceQrUrl($branch),
        ], 'Branch attendance QR regenerated');
    }

    /** @param  array<string, mixed>  $result */
    protected function scanPayload(array $result, User $user): array
    {
        $student = $result['student'];
        $log = $result['log'];

        return [
            'action' => $result['action'],
            'message' => $result['message'],
            'status' => $log->status,
            'time' => $result['time'],
            'check_in' => $log->check_in?->toIso8601String(),
            'check_out' => $log->check_out?->toIso8601String(),
            'student' => [
                'id' => $student->student_code,
                'name' => $student->name,
                'photo' => $student->photo_path,
                'course' => $student->plan_name,
                'branch' => $student->branch?->name,
            ],
            'marked_by' => $user->name,
            'marked_role' => $user->roles->first()?->name,
            'log' => new AttendanceLogResource($log),
        ];
    }

}


