<?php

namespace App\Support;

class HomepageHeroDefaults
{
    public static function all(): array
    {
        return [
            'headline_before' => 'Your Premium',
            'headline_highlight' => 'Study Space',
            'headline_after' => 'Awaits',
            'description' => 'Join {members} serious learners at StudyPoint — India\'s most modern coworking study library. AC halls, high-speed WiFi, biometric access, and flexible plans starting at just {daily_price}/day.',
            'badge_template' => '{members} Active Members Across {branches} Branches',
            'primary_cta_label' => 'Get Started Free',
            'primary_cta_href' => '/admission',
            'secondary_cta_label' => 'View All Plans',
            'secondary_cta_href' => '/plans',
            'quick_stat_1_label' => 'Members This Month',
            'quick_stat_2_label' => 'Avg. Rating',
            'chart_title' => 'Student Growth',
            'chart_stat_1_label' => 'Active',
            'chart_stat_2_label' => 'New Today',
            'chart_stat_3_label' => 'Renewals',
            'stats_bar_1_label' => 'Active Students',
            'stats_bar_2_label' => 'Study Branches',
            'stats_bar_3_label' => 'Active Members',
            'stats_bar_4_label' => 'Years of Trust',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'headline_before' => ['nullable', 'string', 'max:120'],
            'headline_highlight' => ['nullable', 'string', 'max:120'],
            'headline_after' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'badge_template' => ['nullable', 'string', 'max:200'],
            'primary_cta_label' => ['nullable', 'string', 'max:80'],
            'primary_cta_href' => ['nullable', 'string', 'max:200'],
            'secondary_cta_label' => ['nullable', 'string', 'max:80'],
            'secondary_cta_href' => ['nullable', 'string', 'max:200'],
            'quick_stat_1_label' => ['nullable', 'string', 'max:80'],
            'quick_stat_2_label' => ['nullable', 'string', 'max:80'],
            'chart_title' => ['nullable', 'string', 'max:80'],
            'chart_stat_1_label' => ['nullable', 'string', 'max:80'],
            'chart_stat_2_label' => ['nullable', 'string', 'max:80'],
            'chart_stat_3_label' => ['nullable', 'string', 'max:80'],
            'stats_bar_1_label' => ['nullable', 'string', 'max:80'],
            'stats_bar_2_label' => ['nullable', 'string', 'max:80'],
            'stats_bar_3_label' => ['nullable', 'string', 'max:80'],
            'stats_bar_4_label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
