<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanPublicFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $updates = [
            'daily' => [
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access'],
            ],
            'weekly' => [
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'WhatsApp Alerts'],
            ],
            'fortnightly' => [
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access'],
            ],
            'monthly' => [
                'badge' => 'Popular',
                'is_featured' => true,
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'WhatsApp Alerts'],
            ],
            'quarterly' => [
                'badge' => 'Save 11%',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'WhatsApp Alerts'],
            ],
            'half-yearly' => [
                'badge' => 'Save 17%',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'Priority Access', 'WhatsApp Alerts'],
            ],
            'yearly' => [
                'badge' => 'Best Value',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'Priority Access', '24×7 Access', 'WhatsApp Alerts'],
            ],
        ];

        foreach ($updates as $slug => $data) {
            Plan::where('slug', $slug)->update($data);
        }
    }
}
