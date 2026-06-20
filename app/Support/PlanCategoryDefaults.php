<?php

namespace App\Support;

class PlanCategoryDefaults
{
    /** @var array<string, array{duration_days: int, duration_months: int}> */
    private const DURATIONS = [
        'Daily' => ['duration_days' => 1, 'duration_months' => 1],
        'Weekly' => ['duration_days' => 7, 'duration_months' => 1],
        'Fortnightly' => ['duration_days' => 15, 'duration_months' => 1],
        'Monthly' => ['duration_days' => 30, 'duration_months' => 1],
        'Quarterly' => ['duration_days' => 90, 'duration_months' => 3],
        'Half-Yearly' => ['duration_days' => 180, 'duration_months' => 6],
        'Yearly' => ['duration_days' => 365, 'duration_months' => 12],
    ];

    public static function categories(): array
    {
        return array_keys(self::DURATIONS);
    }

    public static function durations(string $category): ?array
    {
        return self::DURATIONS[$category] ?? null;
    }

    public static function apply(array $data): array
    {
        $category = $data['category'] ?? null;
        if (! $category || ! ($durations = self::durations($category))) {
            return $data;
        }

        return array_merge($data, $durations);
    }
}
