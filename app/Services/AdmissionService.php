<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Admission;
use App\Models\AttendanceLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Support\PlanDuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdmissionService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private StudentAccountService $studentAccounts,
        private NotificationDispatchService $notifications,
        private AdmissionDocumentService $documentService,
    ) {}
    public function create(array $data, AdmissionSource $source, ?int $userId = null): Admission
    {
        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;

        $admission = Admission::create([
            'admission_code' => $this->nextCode(),
            'source' => $source,
            'status' => AdmissionStatus::Pending,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'emergency_name' => $data['emergency_name'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'emergency_relation' => $data['emergency_relation'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'plan_id' => $plan?->id,
            'plan_name' => $plan?->name ?? ($data['plan_name'] ?? null),
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'duration_months' => $data['duration_months'] ?? 1,
            'amount' => $data['amount'] ?? ($plan?->price ?? 0),
            'payment_mode' => $data['payment_mode'] ?? null,
            'payment_status' => $this->resolvePaymentStatus($data, $source),
            'transaction_id' => $data['transaction_id'] ?? null,
            'payment_date' => $data['payment_date'] ?? null,
            'documents_uploaded' => (bool) ($data['documents_uploaded'] ?? false),
            'referral_source' => $data['referral_source'] ?? null,
            'notes' => $data['notes'] ?? null,
            'notify_email' => $this->resolveNotifyEmail($data, $source),
            'notify_whatsapp' => $this->resolveNotifyWhatsapp($data, $source),
            'created_by' => $userId,
        ]);

        $this->syncPaymentRecord($admission);

        $admission = $admission->fresh();
        if ($source === AdmissionSource::Online) {
            try {
                $this->notifications->admissionSubmitted($admission);
            } catch (\Throwable) {
                // Admission is saved; notifications are best-effort
            }
        }

        if ($this->shouldAutoApproveAfterPayment($admission)) {
            $this->tryAutoApproveAfterPayment($admission);
            $admission = $admission->fresh();
        }

        return $admission;
    }

    public function syncPaymentRecord(Admission $admission, ?Student $student = null, ?Subscription $subscription = null): ?Payment
    {
        if ((float) $admission->amount <= 0) {
            return null;
        }

        $isPaid = $admission->payment_status === 'paid';
        $attrs = [
            'amount' => $admission->amount,
            'method' => $this->formatPaymentMethod($admission->payment_mode),
            'status' => $isPaid ? 'paid' : 'pending',
            'transaction_id' => $admission->transaction_id,
            'paid_at' => $isPaid ? ($admission->payment_date ?? now()) : null,
            'student_id' => $student?->id ?? $admission->student_id,
            'subscription_id' => $subscription?->id ?? $admission->subscription_id,
        ];

        $payment = Payment::where('admission_id', $admission->id)->first();

        if ($payment) {
            $payment->update($attrs);

            return $payment->fresh();
        }

        return Payment::create([
            'payment_code' => $this->nextPaymentCode(),
            'admission_id' => $admission->id,
            ...$attrs,
        ]);
    }

    public function verify(Admission $admission): Admission
    {
        if ($admission->status !== AdmissionStatus::Pending) {
            throw new \RuntimeException('Only pending admissions can be verified.');
        }

        $admission->update([
            'status' => AdmissionStatus::Verified,
            'verified_at' => now(),
        ]);

        return $admission->fresh();
    }

    public function reject(Admission $admission, ?string $reason = null): Admission
    {
        $admission->update([
            'status' => AdmissionStatus::Rejected,
            'rejection_reason' => $reason ?? 'Documents incomplete',
        ]);

        return $admission->fresh();
    }

    public function approve(Admission $admission): array
    {
        if (! in_array($admission->status, [AdmissionStatus::Pending, AdmissionStatus::Verified], true)) {
            throw new \RuntimeException('Only pending admissions can be approved.');
        }

        if ($admission->payment_status !== 'paid') {
            throw new \RuntimeException('Payment must be collected before approval. Use Collect Payment at counter or complete online payment.');
        }

        $result = DB::transaction(function () use ($admission) {
            $admission->loadMissing('plan');
            $endDate = PlanDuration::endDateForAdmission($admission);
            $studentCode = $this->nextStudentCode();
            $qrToken = AttendanceService::generateQrToken();

            $student = Student::create([
                'student_code' => $studentCode,
                'verify_token' => $qrToken,
                'qr_token' => $qrToken,
                'name' => $admission->name,
                'email' => $admission->email,
                'phone' => $admission->phone,
                'branch_id' => $admission->branch_id,
                'plan_id' => $admission->plan_id,
                'city' => $admission->city,
                'emergency_contact' => $admission->emergency_phone
                    ? ($admission->emergency_name ?? 'Contact') . ' · ' . $admission->emergency_phone
                    : null,
                'plan_name' => $admission->plan_name,
                'status' => $admission->payment_status === 'paid' ? StudentStatus::Active : StudentStatus::Pending,
                'admission_id' => $admission->id,
                'valid_from' => $admission->start_date,
                'expiry' => $endDate,
            ]);

            $subscription = $this->subscriptions->createFromAdmission($admission, $student);
            $this->studentAccounts->ensureForStudent($student->fresh());

            $admission->update([
                'status' => AdmissionStatus::Active,
                'student_id' => $student->id,
                'subscription_id' => $subscription->id,
                'approved_at' => now(),
            ]);

            $this->syncPaymentRecord($admission->fresh(), $student, $subscription);

            $student = $student->fresh(['user']);

            return [
                'student' => $student,
                'subscription' => $subscription->fresh(),
                'admission' => $admission->fresh(['branch', 'plan', 'documents', 'student']),
            ];
        });

        app(BiometricAccessService::class)->syncStudentAccess($result['student']);

        return $result;
    }

    public function shouldAutoApproveAfterPayment(Admission $admission): bool
    {
        return in_array($admission->status, [AdmissionStatus::Pending, AdmissionStatus::Verified], true)
            && $admission->payment_status === 'paid';
    }

    public function tryAutoApproveAfterPayment(Admission $admission): ?array
    {
        if (! $this->shouldAutoApproveAfterPayment($admission)) {
            return null;
        }

        return $this->approve($admission);
    }

    public function finalizeAfterPayment(Admission $admission): void
    {
        if ($admission->payment_status !== 'paid') {
            return;
        }

        if (! $admission->student_id) {
            $this->tryAutoApproveAfterPayment($admission);

            return;
        }

        $admission->update(['status' => AdmissionStatus::Active]);
        $this->subscriptions->activateMembership(
            $admission->student,
            $admission->subscription,
        );

        if ($admission->student) {
            $this->subscriptions->syncStudentMembership($admission->student);
        }
    }

    public function deleteCascade(Admission $admission): array
    {
        return DB::transaction(function () use ($admission) {
            $student = $this->resolveStudentForAdmission($admission);

            $summary = [
                'admission_code' => $admission->admission_code,
                'permanent' => true,
                'deleted' => [
                    'documents' => $this->documentService->deleteForAdmission($admission),
                    'payments' => 0,
                    'invoices' => 0,
                    'subscriptions' => 0,
                    'attendance_logs' => 0,
                    'student' => false,
                    'portal_user' => false,
                    'admission' => true,
                ],
            ];

            $paymentQuery = Payment::withTrashed()->where(function ($query) use ($admission, $student) {
                $query->where('admission_id', $admission->id);
                if ($student) {
                    $query->orWhere('student_id', $student->id);
                }
            });

            $paymentIds = (clone $paymentQuery)->pluck('id');

            if ($paymentIds->isNotEmpty()) {
                $summary['deleted']['invoices'] += Invoice::withTrashed()
                    ->whereIn('payment_id', $paymentIds)
                    ->forceDelete();
            }

            if ($student) {
                $summary['deleted']['invoices'] += Invoice::withTrashed()
                    ->where('student_id', $student->id)
                    ->forceDelete();

                $subscriptionIds = Subscription::withTrashed()
                    ->where('student_id', $student->id)
                    ->pluck('id');
                if ($admission->subscription_id) {
                    $subscriptionIds = $subscriptionIds
                        ->push($admission->subscription_id)
                        ->unique()
                        ->values();
                }

                foreach ($subscriptionIds as $subscriptionId) {
                    if (Subscription::withTrashed()->where('id', $subscriptionId)->forceDelete()) {
                        $summary['deleted']['subscriptions']++;
                    }
                }

                $summary['deleted']['attendance_logs'] = AttendanceLog::where('student_id', $student->id)->delete();
                $summary['deleted']['portal_user'] = $this->studentAccounts->deletePortalUser($student);

                if ($student->photo_path) {
                    Storage::disk('public')->delete($student->photo_path);
                }

                $student->forceDelete();
                $summary['deleted']['student'] = true;
            }

            $summary['deleted']['payments'] = $paymentQuery->forceDelete();
            $admission->forceDelete();

            return $summary;
        });
    }

    public function reconcileUnpaidActivations(): void
    {
        $admissions = Admission::query()
            ->where('payment_status', 'pending')
            ->where(function ($query) {
                $query->where('status', AdmissionStatus::Active)
                    ->orWhereNotNull('student_id');
            })
            ->with(['student', 'subscription'])
            ->get();

        foreach ($admissions as $admission) {
            if (Payment::where('admission_id', $admission->id)->where('status', 'paid')->exists()) {
                continue;
            }

            if ($admission->status === AdmissionStatus::Active) {
                $admission->update(['status' => AdmissionStatus::Pending]);
            }

            $admission->student?->update(['status' => StudentStatus::Pending]);
            $admission->subscription?->update(['status' => SubscriptionStatus::Pending]);
        }
    }

    private function resolveStudentForAdmission(Admission $admission): ?Student
    {
        if ($admission->student_id) {
            return Student::withTrashed()->with('user')->find($admission->student_id);
        }

        return Student::withTrashed()->with('user')->where('admission_id', $admission->id)->first();
    }

    private function resolvePaymentStatus(array $data, AdmissionSource $source): string
    {
        if (isset($data['payment_status']) && in_array($data['payment_status'], ['pending', 'paid'], true)) {
            return $data['payment_status'];
        }

        // Admin/branch counter: paid only when collection is recorded
        if (in_array($source, [AdmissionSource::Admin, AdmissionSource::Branch], true)) {
            if (! empty($data['payment_date']) || ! empty($data['transaction_id'])) {
                return 'paid';
            }

            return 'pending';
        }

        // Online: selecting UPI/card is intent only — not a completed payment
        return 'pending';
    }

    private function resolveNotifyEmail(array $data, AdmissionSource $source): bool
    {
        if ($source === AdmissionSource::Online) {
            return (bool) ($data['notify_email'] ?? false);
        }

        return (bool) ($data['notify_email'] ?? true);
    }

    private function resolveNotifyWhatsapp(array $data, AdmissionSource $source): bool
    {
        if ($source === AdmissionSource::Online) {
            return (bool) ($data['notify_whatsapp'] ?? false);
        }

        return (bool) ($data['notify_whatsapp'] ?? true);
    }

    private function nextCode(): string
    {
        $last = Admission::withTrashed()->orderByDesc('id')->value('admission_code');
        $num = $last ? (int) preg_replace('/\D/', '', $last) + 1 : 1;

        return 'ADM' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }

    private function nextStudentCode(): string
    {
        $last = Student::withTrashed()->orderByDesc('id')->value('student_code');
        $num = $last ? (int) preg_replace('/\D/', '', $last) + 1 : 2024248;

        return 'SP' . $num;
    }

    private function nextPaymentCode(): string
    {
        $year = date('Y');
        $last = Payment::withTrashed()
            ->where('payment_code', 'like', "PAY-{$year}-%")
            ->orderByDesc('id')
            ->value('payment_code');
        $num = $last ? (int) substr($last, -3) + 1 : 1;

        return 'PAY-' . $year . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }

    private function formatPaymentMethod(?string $mode): string
    {
        return match ($mode) {
            'upi' => 'UPI',
            'card' => 'Card',
            'netbanking' => 'Net Banking',
            'branch' => 'Cash',
            default => $mode ? ucfirst($mode) : 'Pending',
        };
    }
}
