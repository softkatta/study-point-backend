<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Services\SmartOfficeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'student_code', 'verify_token', 'qr_token', 'user_id', 'name', 'email', 'phone',
        'branch_id', 'plan_id', 'city', 'blood_group', 'emergency_contact', 'photo_path',
        'plan_name', 'status', 'admission_id', 'valid_from', 'expiry',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'valid_from' => 'date',
            'expiry' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findByEmployeeCode(string $employeeCode): ?self
    {
        $target = SmartOfficeService::employeeCode($employeeCode);
        if ($target === '') {
            return null;
        }

        return static::query()
            ->whereNotNull('student_code')
            ->get()
            ->first(fn (self $student) => SmartOfficeService::employeeCode($student->student_code) === $target);
    }

    public function hasReceivedPayment(): bool
    {
        $this->loadMissing('admission');

        if ($this->admission) {
            return $this->admission->payment_status === 'paid';
        }

        return $this->payments()->where('status', 'paid')->exists();
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('student_code', $value)
            ->orWhere('id', $value)
            ->first();
    }
}
