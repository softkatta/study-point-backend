<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What are the operating hours?',
                'answer' => 'Most StudyPoint branches are open from 6:00 AM to 11:00 PM, seven days a week. Check the Branches page for branch-specific timings.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Can I switch between branches?',
                'answer' => 'Your primary branch is assigned at admission. Short-term branch transfers may be arranged through the branch manager for eligible plans.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Is WiFi included in all plans?',
                'answer' => 'Yes. High-speed WiFi is included with every membership plan at no extra charge.',
                'sort_order' => 3,
            ],
            [
                'question' => 'How do I renew my membership?',
                'answer' => 'You can renew from the student portal before expiry, or visit your branch counter. Online payment and UPI are supported.',
                'sort_order' => 4,
            ],
            [
                'question' => 'What documents are needed for admission?',
                'answer' => 'A valid photo ID (Aadhaar, college ID or driving licence) and a recent passport-size photograph are required for new admissions.',
                'sort_order' => 5,
            ],
        ];

        foreach ($faqs as $data) {
            Faq::updateOrCreate(
                ['question' => $data['question']],
                [
                    ...$data,
                    'status' => 'active',
                ],
            );
        }
    }
}
