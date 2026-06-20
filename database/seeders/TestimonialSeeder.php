<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Priya Sharma',
                'role' => 'UPSC Aspirant',
                'quote' => 'StudyPoint transformed my preparation. The peaceful environment and 24×7 access helped me crack my exam in the first attempt.',
                'rating' => 5,
                'avatar' => 'PS',
                'sort_order' => 1,
            ],
            [
                'name' => 'Rahul Mehta',
                'role' => 'CA Student',
                'quote' => 'Best study library in the city! The individual cabins and high-speed WiFi make it perfect for online exams and study sessions.',
                'rating' => 5,
                'avatar' => 'RM',
                'sort_order' => 2,
            ],
            [
                'name' => 'Sneha Patel',
                'role' => 'NEET Aspirant',
                'quote' => 'Very affordable monthly plans and the staff is very supportive. The biometric entry system is super convenient.',
                'rating' => 5,
                'avatar' => 'SP',
                'sort_order' => 3,
            ],
        ];

        foreach ($rows as $row) {
            Testimonial::updateOrCreate(
                ['name' => $row['name'], 'role' => $row['role']],
                $row + ['status' => 'active'],
            );
        }
    }
}
