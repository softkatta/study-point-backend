<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'code', 'name', 'legal_name', 'registration_number', 'city', 'state', 'pincode',
        'manager_name', 'manager_phone', 'email', 'website',
        'timezone', 'currency', 'currency_symbol',
        'address', 'operating_hours', 'opens_at', 'closes_at',
        'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube',
        'pan', 'gstin', 'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
        'gst_registration_type', 'gst_filing_frequency', 'gst_reverse_charge',
        'features', 'is_accepting_admissions', 'is_head_office',
        'capacity', 'status', 'attendance_qr_token', 'revenue',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'features' => 'array',
            'is_accepting_admissions' => 'boolean',
            'is_head_office' => 'boolean',
            'gst_reverse_charge' => 'boolean',
            'gst_rate' => 'decimal:2',
            'cgst_rate' => 'decimal:2',
            'sgst_rate' => 'decimal:2',
            'igst_rate' => 'decimal:2',
            'capacity' => 'integer',
        ];
    }

    public function displayOperatingHours(): ?string
    {
        if ($this->opens_at && $this->closes_at) {
            return trim($this->opens_at).' – '.trim($this->closes_at);
        }

        return $this->operating_hours;
    }

    public function scopeHeadOffice($query)
    {
        return $query->where('is_head_office', true);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id')
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'branch_manager'));
    }

    public function assignedManagerName(): ?string
    {
        if ($this->relationLoaded('managers')) {
            return $this->managers->first()?->name;
        }

        return $this->managers()->value('name');
    }

    public function enrolledCount(): int
    {
        if (isset($this->students_count)) {
            return (int) $this->students_count;
        }

        return (int) $this->students()->count();
    }

    public function enrollmentOpen(): int
    {
        if ($this->status !== 'active' || ! $this->is_accepting_admissions) {
            return 0;
        }

        return max(0, (int) $this->capacity - $this->enrolledCount());
    }

    public function enrollmentDisplayStatus(): string
    {
        return $this->enrollmentOpen() > 0 ? 'open' : 'full';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('code', $value)
            ->orWhere('id', $value)
            ->first();
    }
}
