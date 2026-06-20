<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Branch;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Testimonial;
use Carbon\Carbon;

class HomePageStatsService
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $activeStudents = Student::query()->where('status', StudentStatus::Active)->count();
        $activeBranches = Branch::query()->where('status', 'active')->count();
        $membersThisMonth = Student::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $avgRating = round((float) (Testimonial::query()->where('status', 'active')->avg('rating') ?? 5), 1);
        $newToday = Student::query()->whereDate('created_at', today())->count();
        $renewalsThisMonth = Subscription::query()
            ->where('status', 'active')
            ->where('start_date', '>=', now()->startOfMonth()->toDateString())
            ->count();

        $thisMonthStudents = Student::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $lastMonthStudents = Student::query()
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->count();
        $growthPercent = $lastMonthStudents > 0
            ? (int) round((($thisMonthStudents - $lastMonthStudents) / $lastMonthStudents) * 100)
            : ($thisMonthStudents > 0 ? 100 : 0);

        $growthChart = collect(range(5, 0))->map(function (int $monthsAgo) {
            $end = now()->subMonths($monthsAgo)->endOfMonth();

            return [
                'month' => $end->format('M'),
                'students' => Student::query()
                    ->where('created_at', '<=', $end)
                    ->count(),
            ];
        })->values()->all();

        $foundedAt = Branch::query()->min('created_at')
            ?? Student::query()->min('created_at')
            ?? now()->subYears(6)->toDateTimeString();
        $yearsTrust = max(1, (int) Carbon::parse($foundedAt)->diffInYears(now()));

        return [
            'active_students' => $activeStudents,
            'active_branches' => $activeBranches,
            'active_members' => $activeStudents,
            'members_this_month' => $membersThisMonth,
            'avg_rating' => $avgRating,
            'growth_percent' => $growthPercent,
            'growth_chart' => $growthChart,
            'new_today' => $newToday,
            'renewals_this_month' => $renewalsThisMonth,
            'years_of_trust' => $yearsTrust,
        ];
    }
}
