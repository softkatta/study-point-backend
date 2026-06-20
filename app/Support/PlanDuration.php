<?php

namespace App\Support;

use App\Models\Admission;
use App\Models\Plan;
use Carbon\Carbon;

class PlanDuration
{
    public static function endDate(
        Carbon|string $startDate,
        ?Plan $plan = null,
        ?int $durationDays = null,
        ?int $durationMonths = null,
    ): string {
        $start = Carbon::parse($startDate);
        $days = self::resolveDays($plan, $durationDays, $durationMonths);

        return $start->copy()->addDays(max(1, $days) - 1)->toDateString();
    }

    public static function endDateForAdmission(Admission $admission): string
    {
        $admission->loadMissing('plan');

        return self::endDate(
            $admission->start_date,
            $admission->plan,
            $admission->plan?->duration_days,
            $admission->duration_months,
        );
    }

    public static function endDateForPlan(Carbon|string $startDate, Plan $plan): string
    {
        return self::endDate($startDate, $plan);
    }

    private static function resolveDays(?Plan $plan, ?int $durationDays, ?int $durationMonths): int
    {
        if ($plan && (int) $plan->duration_days > 0) {
            return (int) $plan->duration_days;
        }

        if ($durationDays && $durationDays > 0) {
            return $durationDays;
        }

        if ($plan?->category && ($defaults = PlanCategoryDefaults::durations($plan->category))) {
            return (int) $defaults['duration_days'];
        }

        $months = $durationMonths ?? $plan?->duration_months ?? 1;

        return max(1, (int) $months) * 30;
    }
}
