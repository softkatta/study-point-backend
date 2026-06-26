<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $facilities = [
            [
                'slug' => 'ac-study-hall',
                'title' => 'AC Study Hall',
                'short_description' => 'Fully air-conditioned halls designed for long, focused study sessions.',
                'description' => 'Spacious, well-lit study halls with ergonomic seating and a quiet, distraction-free environment.',
                'bullet_points' => ['24/7 climate control', 'Ergonomic seating', 'Quiet zones', 'Dedicated desk spaces'],
                'icon' => 'coffee',
                'sort_order' => 1,
            ],
            [
                'slug' => 'high-speed-wifi',
                'title' => 'High-Speed WiFi',
                'short_description' => 'Dedicated fibre internet for online classes, research and downloads.',
                'description' => 'Enterprise-grade WiFi with ample bandwidth for video lectures, cloud notes and competitive exam portals.',
                'bullet_points' => ['Fibre backbone', 'Separate student network', 'Power backup for routers', 'Stable connectivity'],
                'icon' => 'wifi',
                'sort_order' => 2,
            ],
            [
                'slug' => 'biometric-access',
                'title' => 'Biometric Access',
                'short_description' => 'Secure fingerprint entry for members only.',
                'description' => 'Contactless biometric check-in keeps the study space safe and tracks attendance automatically.',
                'bullet_points' => ['Fingerprint entry', 'Member-only access', 'Attendance tracking', 'Secure premises'],
                'icon' => 'users',
                'sort_order' => 3,
            ],
            [
                'slug' => 'library-access',
                'title' => 'Library & Reading Zone',
                'short_description' => 'Curated reference books and a calm reading corner.',
                'description' => 'Access to competitive exam guides, magazines and a dedicated silent reading area.',
                'bullet_points' => ['Reference books', 'Silent reading zone', 'Magazine rack', 'Study material support'],
                'icon' => 'book-open',
                'sort_order' => 4,
            ],
            [
                'slug' => 'cctv-security',
                'title' => 'CCTV Security',
                'short_description' => 'Round-the-clock surveillance for a safe study environment.',
                'description' => 'CCTV coverage across common areas with trained staff on duty during operating hours.',
                'bullet_points' => ['24/7 monitoring', 'Trained staff', 'Secure lockers', 'Emergency protocols'],
                'icon' => 'shield',
                'sort_order' => 5,
            ],
        ];

        foreach ($facilities as $data) {
            Facility::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'show_in_nav' => true,
                    'show_on_home' => true,
                    'show_on_page' => true,
                    'status' => 'active',
                ],
            );
        }
    }
}
