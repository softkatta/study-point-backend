<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $highlights = [
            'AC Study Hall',
            'High-Speed WiFi',
            'Biometric Access',
            'Library Access',
        ];

        $plans = [
            [
                'slug' => 'daily',
                'name' => 'Daily Pass',
                'category' => 'Daily',
                'duration_days' => 1,
                'duration_months' => 1,
                'price' => 99,
                'description' => 'Perfect for a focused single-day study session.',
            ],
            [
                'slug' => 'weekly',
                'name' => 'Weekly Pass',
                'category' => 'Weekly',
                'duration_days' => 7,
                'duration_months' => 1,
                'price' => 499,
                'description' => 'Seven days of uninterrupted access to premium study halls.',
            ],
            [
                'slug' => 'fortnightly',
                'name' => 'Fortnightly Pass',
                'category' => 'Fortnightly',
                'duration_days' => 15,
                'duration_months' => 1,
                'price' => 899,
                'description' => 'Fifteen days of flexible study access at great value.',
            ],
            [
                'slug' => 'monthly',
                'name' => 'Monthly Pass',
                'category' => 'Monthly',
                'duration_days' => 30,
                'duration_months' => 1,
                'price' => 1499,
                'description' => 'Our most popular plan for serious exam preparation.',
                'badge' => 'Most Popular',
                'is_featured' => true,
            ],
            [
                'slug' => 'quarterly',
                'name' => 'Quarterly Pass',
                'category' => 'Quarterly',
                'duration_days' => 90,
                'duration_months' => 3,
                'price' => 3999,
                'description' => 'Three months of premium study space with savings.',
            ],
            [
                'slug' => 'half-yearly',
                'name' => 'Half Yearly Pass',
                'category' => 'Half-Yearly',
                'duration_days' => 180,
                'duration_months' => 6,
                'price' => 7499,
                'description' => 'Six months of uninterrupted study access.',
            ],
            [
                'slug' => 'yearly',
                'name' => 'Annual Pass',
                'category' => 'Yearly',
                'duration_days' => 365,
                'duration_months' => 12,
                'price' => 12999,
                'description' => 'Best value for long-term learners — full year access.',
                'badge' => 'Best Value',
                'is_featured' => true,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'status' => 'active',
                    'highlights' => $highlights,
                    'is_featured' => $data['is_featured'] ?? false,
                    'badge' => $data['badge'] ?? null,
                ],
            );
        }
    }
}
