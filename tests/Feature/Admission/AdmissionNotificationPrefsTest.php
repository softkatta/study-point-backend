<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\MailSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdmissionNotificationPrefsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_notification_channels_endpoint(): void
    {
        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $response = $this->getJson('/api/v1/notification/channels');
        $response->assertOk()
            ->assertJsonPath('data.email', true)
            ->assertJsonPath('data.whatsapp', false);
    }

    public function test_online_admission_requires_notification_selection_when_configured(): void
    {
        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $response = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Notify',
            'email' => 'notify@test.com',
            'phone' => '9999999940',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'notify_email' => false,
            'notify_whatsapp' => false,
            'source' => 'online',
        ]);

        $response->assertStatus(422);
    }

    public function test_online_admission_stores_selected_notification_channels(): void
    {
        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);
        Setting::saveSection('whatsapp', [
            'provider' => 'interakt',
            'interakt_api_key' => 'test-key',
        ]);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $response = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Notify',
            'email' => 'notify-prefs@test.com',
            'phone' => '9999999941',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'notify_email' => true,
            'notify_whatsapp' => false,
            'source' => 'online',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.notify_email', true)
            ->assertJsonPath('data.notify_whatsapp', false);

        $this->assertDatabaseHas('admissions', [
            'email' => 'notify-prefs@test.com',
            'notify_email' => true,
            'notify_whatsapp' => false,
        ]);
    }

    public function test_online_unpaid_admission_sends_welcome_email(): void
    {
        $mailer = Mockery::mock(MailSenderService::class);
        $mailer->shouldReceive('isConfigured')->andReturn(true);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $message, $html, array $template, array $attachments = []) {
                return $to === 'welcome-stage1@test.com'
                    && str_contains($subject, 'Admission Received')
                    && ($template['cta_label'] ?? '') === 'Make payment'
                    && str_contains((string) ($template['cta_url'] ?? ''), '/admission/pay?');
            });
        $this->instance(MailSenderService::class, $mailer);

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $response = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Welcome',
            'email' => 'welcome-stage1@test.com',
            'phone' => '9999999942',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'notify_email' => true,
            'notify_whatsapp' => false,
            'source' => 'online',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_collect_payment_sends_activation_email_not_welcome(): void
    {
        $mailer = Mockery::mock(MailSenderService::class);
        $mailer->shouldReceive('isConfigured')->andReturn(true);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $message, $html, array $template, array $attachments = []) {
                return $to === 'stage-two@test.com'
                    && str_contains($subject, 'Payment Confirmed')
                    && count($attachments) === 1
                    && ($attachments[0]['mime'] ?? '') === 'application/pdf';
            });
        $this->instance(MailSenderService::class, $mailer);

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = \App\Models\User::where('email', 'admin@studypoint.in')->first();
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'StageTwo',
            'email' => 'stage-two@test.com',
            'phone' => '9999999943',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'notify_email' => true,
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ])->assertOk();
    }
}
