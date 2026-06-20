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
                'answer' => 'Most branches operate from 6 AM to 11 PM. Annual members get 24×7 access with biometric entry.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Can I bring food inside?',
                'answer' => 'Light snacks are allowed in the cafeteria area. Meals are not permitted inside the study halls to maintain hygiene.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Is there a trial period?',
                'answer' => 'Yes! You can get a Daily Pass to experience our facilities before committing to a longer plan.',
                'sort_order' => 3,
            ],
            [
                'question' => 'How does biometric access work?',
                'answer' => 'After enrollment, your fingerprint or face is registered. Simply scan at the entry gate — the system validates your membership automatically.',
                'sort_order' => 4,
            ],
            [
                'question' => 'Can I switch branches?',
                'answer' => 'Multi-branch access is available with Quarterly, Half-Yearly, and Annual plans. Single-branch plans are limited to one branch.',
                'sort_order' => 5,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq + ['status' => 'active'],
            );
        }
    }
}
