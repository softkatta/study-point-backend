<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Priya Sharma',
                'role' => 'UPSC Aspirant',
                'quote' => 'StudyPoint gave me the discipline and environment I needed. The AC halls and quiet zones helped me stay focused for 10+ hours daily.',
                'rating' => 5,
                'avatar' => 'PS',
                'sort_order' => 1,
            ],
            [
                'name' => 'Rahul Mehta',
                'role' => 'Engineering Student',
                'quote' => 'Affordable plans, great WiFi and biometric entry make it feel premium. I renewed my monthly pass three times in a row.',
                'rating' => 5,
                'avatar' => 'RM',
                'sort_order' => 2,
            ],
            [
                'name' => 'Ananya Joshi',
                'role' => 'CA Final Candidate',
                'quote' => 'The staff is supportive and the study halls are always clean. Best study library I have used in Mumbai.',
                'rating' => 5,
                'avatar' => 'AJ',
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['name' => $data['name'], 'role' => $data['role']],
                [
                    ...$data,
                    'status' => 'active',
                ],
            );
        }
    }
}
