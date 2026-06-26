<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\AttendanceLog;
use App\Models\Student;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\StudentAccountService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\AttendanceGate;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        private AdmissionService $admissions,
        private StudentAccountService $studentAccounts,
        private SubscriptionService $subscriptions,
        private AttendanceService $attendance,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->admissions->reconcileUnpaidActivations();

        $query = Student::with('branch')->latest();
        BranchScope::apply($query, $request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('student_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success(
            StudentResource::collection($query->paginate($request->integer('per_page', 15)))
        );
    }

    public function show(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $this->subscriptions->syncExpiryStatuses();
        $this->subscriptions->syncStudentMembership($student);

        return ApiResponse::success(new StudentResource($student->fresh()->load([
            'branch',
            'subscriptions' => fn ($q) => $q->withExists(['payments as has_collected_payment' => function ($query) {
                $query->whereIn('status', ['paid', 'refund_pending', 'refunded']);
            }])->latest(),
            'admission',
            'user',
        ])));
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $student);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email'],
            'branch_id' => ['sometimes', 'exists:branches,id'],
            'city' => ['nullable', 'string'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'emergency_contact' => ['nullable', 'string'],
        ]);

        if (BranchScope::branchId($request->user())) {
            unset($data['branch_id']);
        }

        $student->update($data);

        return ApiResponse::success(new StudentResource($student->fresh('branch')), 'Student updated');
    }

    public function destroy(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $student->delete();

        return ApiResponse::success(null, 'Student deleted');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $query = Student::whereIn('id', $request->ids);
        BranchScope::apply($query, $request->user());
        $query->delete();

        return ApiResponse::success(null, 'Students deleted');
    }

    public function activate(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $student->loadMissing('admission');
        if ($student->admission && $student->admission->payment_status !== 'paid') {
            return ApiResponse::error('Cannot activate student until admission payment is collected.', 422);
        }

        $student->update(['status' => StudentStatus::Active]);

        return ApiResponse::success(new StudentResource($student->fresh()), 'Student activated');
    }

    public function deactivate(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $student->update(['status' => StudentStatus::Pending]);

        return ApiResponse::success(new StudentResource($student->fresh()), 'Student marked inactive');
    }

    public function suspend(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $student->update(['status' => StudentStatus::Blacklisted]);

        return ApiResponse::success(new StudentResource($student->fresh()), 'Student blocked');
    }

    public function resendPortalCredentials(Student $student): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $student);

        $student->loadMissing('admission');
        if (! $student->hasReceivedPayment()) {
            return ApiResponse::error('Collect admission payment before sending portal credentials.', 422);
        }

        try {
            $result = $this->studentAccounts->resendPortalCredentials($student);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Could not send welcome email. Check Admin → Settings → Email (SMTP) and try again.',
                422,
            );
        }

        if (! $result['credentials_sent']) {
            return ApiResponse::error(
                'Could not send welcome email. Check Admin → Settings → Email (SMTP) and try again.',
                422,
            );
        }

        $email = $result['email'];
        $message = $result['credentials_sent']
            ? "Welcome email sent to {$email}. Ask the student to check Inbox and Spam/Promotions."
            : "Portal account updated but notification could not be sent. Check email settings.";

        return ApiResponse::success($result, $message);
    }

    public function verify(Request $request, string $token): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        $this->subscriptions->syncExpiryStatuses();

        $student = $this->attendance->findStudentByToken($token);

        if (! $student) {
            return ApiResponse::error('Student not found', 404);
        }

        AttendanceGate::ensure($request->user(), $student);

        $this->subscriptions->syncStudentMembership($student);
        $student = $student->fresh(['branch', 'subscriptions' => fn ($q) => $q->latest()]);
        $subscription = $student->subscriptions->first();

        if ($subscription) {
            $student->setAttribute('plan_name', $subscription->plan_name);
            $student->setAttribute('valid_from', $subscription->start_date);
            $student->setAttribute('expiry', $subscription->end_date);
        } else {
            $student->setAttribute('plan_name', null);
            $student->setAttribute('valid_from', null);
            $student->setAttribute('expiry', null);
        }

        $todayLog = AttendanceLog::where('student_id', $student->id)
            ->whereDate('check_in', now()->toDateString())
            ->latest('check_in')
            ->first();

        $messages = [
            'active' => 'Student membership is active and valid for branch entry.',
            'pending' => 'Membership is pending activation. Access not granted yet.',
            'expired' => 'Membership has expired. Student must renew before entry.',
            'blacklisted' => 'Student access has been suspended by administration.',
        ];

        $status = $student->status->value;
        $entryAllowed = false;
        $validationMessage = null;

        try {
            $this->attendance->validateStudentForAttendance($student);
            $entryAllowed = true;
        } catch (\RuntimeException $e) {
            $validationMessage = $e->getMessage();
        }

        $message = $entryAllowed
            ? ($messages[$status] ?? 'Student membership is active and valid for branch entry.')
            : ($validationMessage ?? ($messages[$status] ?? 'Access denied.'));

        return ApiResponse::success([
            'verified' => $entryAllowed,
            'entry_allowed' => $entryAllowed,
            'message' => $message,
            'student' => new StudentResource($student),
            'subscription' => $subscription ? [
                'plan_name' => $subscription->plan_name,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'status' => $subscription->status->value,
            ] : null,
            'attendance_today' => $todayLog ? [
                'check_in' => $todayLog->check_in?->toIso8601String(),
                'check_out' => $todayLog->check_out?->toIso8601String(),
            ] : null,
        ]);
    }

    public function qrCheckIn(Request $request, Student $student): JsonResponse
    {
        AttendanceGate::ensure($request->user(), $student);

        $log = $this->attendance->checkIn($student, 'qr');

        return ApiResponse::success(['log_id' => $log->id, 'time' => $log->check_in->toIso8601String()], 'Check-in recorded');
    }

    public function qrCheckOut(Request $request, Student $student): JsonResponse
    {
        AttendanceGate::ensure($request->user(), $student);

        $log = $this->attendance->checkOut($student);

        return ApiResponse::success(['time' => ($log?->check_out ?? now())->toIso8601String()], 'Check-out recorded');
    }

    public function tokenCheckIn(Request $request, string $token): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        try {
            $result = $this->attendance->scanStudentQr($token, $request->user());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $log = $result['log'];
        $student = $result['student'];

        return ApiResponse::success([
            'action' => $result['action'],
            'log_id' => $log->id,
            'time' => $result['time'],
            'check_in' => $log->check_in?->toIso8601String(),
            'check_out' => $log->check_out?->toIso8601String(),
            'status' => $log->status,
            'student_code' => $student->student_code,
            'student_name' => $student->name,
            'course' => $student->plan_name,
            'branch' => $student->branch?->name,
        ], $result['message']);
    }

    public function tokenCheckOut(Request $request, string $token): JsonResponse
    {
        AttendanceGate::ensure($request->user());

        try {
            $result = $this->attendance->scanStudentQr($token, $request->user());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $log = $result['log'];
        $student = $result['student'];

        return ApiResponse::success([
            'action' => $result['action'],
            'time' => $result['time'],
            'check_in' => $log->check_in?->toIso8601String(),
            'check_out' => $log->check_out?->toIso8601String(),
            'student_code' => $student->student_code,
        ], $result['message']);
    }
}
