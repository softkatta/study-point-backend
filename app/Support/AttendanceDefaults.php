<?php

namespace App\Support;

class AttendanceDefaults
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            'attendance_start' => '09:00',
            'late_after' => '09:15',
            'half_day_after' => '12:00',
            'allow_duplicate_scan' => false,
        ];
    }

    /** @param  array<string, mixed>|null  $data */
    public static function merge(?array $data): array
    {
        return array_merge(self::all(), $data ?? []);
    }
}
