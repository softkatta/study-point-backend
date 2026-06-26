<?php

namespace App\Models;

use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    protected $fillable = [
        'admission_code', 'source', 'status', 'first_name', 'last_name', 'name',
        'email', 'phone', 'date_of_birth', 'gender', 'address', 'city', 'state', 'pincode',
        'emergency_name', 'emergency_phone', 'emergency_relation',
        'branch_id', 'plan_id', 'plan_name', 'start_date', 'duration_months', 'amount',
        'payment_mode', 'payment_status', 'transaction_id', 'payment_date',
        'documents_uploaded', 'referral_source', 'notes', 'notify_email', 'notify_whatsapp',
        'follow_up_date', 'follow_up_note', 'rejection_reason',
        'verified_at', 'approved_at', 'student_id', 'subscription_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'source' => AdmissionSource::class,
            'documents_uploaded' => 'boolean',
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'payment_date' => 'date',
            'follow_up_date' => 'date',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
