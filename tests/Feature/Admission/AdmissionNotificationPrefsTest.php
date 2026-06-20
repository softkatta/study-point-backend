<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
