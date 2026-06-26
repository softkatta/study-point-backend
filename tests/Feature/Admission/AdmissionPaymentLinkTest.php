<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Setting;
use App\Support\AdmissionPaymentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionPaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_resume_payment_returns_session_for_valid_signed_link(): void
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
            'first_name' => 'PayLink',
            'email' => 'pay-link@test.com',
            'phone' => '9999999944',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'notify_email' => true,
            'notify_whatsapp' => false,
            'source' => 'online',
        ]);

        $admissionId = $response->json('data.id');
        $signed = AdmissionPaymentLink::sign($admissionId);

        $this->getJson("/api/v1/admissions/{$admissionId}/resume-payment?".http_build_query($signed))
            ->assertOk()
            ->assertJsonPath('data.admission_id', $admissionId)
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.can_pay_online', true);
    }

    public function test_resume_payment_rejects_invalid_signature(): void
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
            'first_name' => 'Invalid',
            'email' => 'invalid-sig@test.com',
            'phone' => '9999999945',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'notify_email' => true,
            'notify_whatsapp' => false,
            'source' => 'online',
        ]);

        $admissionId = $response->json('data.id');

        $this->getJson("/api/v1/admissions/{$admissionId}/resume-payment?expires=".now()->addDay()->timestamp.'&signature=invalid')
            ->assertStatus(403);
    }
}
