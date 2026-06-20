<?php

namespace Tests\Feature;

use App\Services\EmailTemplateService;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    public function test_study_point_email_template_renders_branded_layout(): void
    {
        $html = app(EmailTemplateService::class)->render([
            'title' => 'Welcome to our family!',
            'eyebrow' => 'Welcome',
            'paragraphs' => [
                'Your StudyPoint account is ready.',
                'Please sign in using the credentials below.',
            ],
            'details' => [
                ['label' => 'Member ID', 'value' => 'STU-1001'],
                ['label' => 'Email', 'value' => 'student@example.com'],
            ],
            'cta_label' => 'Sign in',
            'cta_url' => 'https://studypoint.in/login',
            'preheader' => 'Your StudyPoint account is ready.',
        ]);

        $this->assertStringContainsString('Welcome to our family!', $html);
        $this->assertStringContainsString('STU-1001', $html);
        $this->assertStringContainsString('student@example.com', $html);
        $this->assertStringContainsString('#3b5bdb', $html);
        $this->assertStringContainsString('Sign in', $html);
        $this->assertStringContainsString('View online', $html);
        $this->assertStringContainsString('Share our message', $html);
        $this->assertStringContainsString('border-radius:16px', $html);
    }
}
