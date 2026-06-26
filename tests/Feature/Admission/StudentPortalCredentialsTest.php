<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentPortalCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_resend_portal_credentials(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Resend',
            'email' => 'resend@test.com',
            'phone' => '9999999930',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $create->assertCreated();

        $student = Student::where('email', 'resend@test.com')->first();
        $this->assertNotNull($student->user_id);

        $response = $this->postJson("/api/v1/students/{$student->id}/resend-portal-credentials");
        $response->assertOk()
            ->assertJsonPath('data.email', 'resend@test.com')
            ->assertJsonPath('data.portal_ready', true)
            ->assertJsonPath('data.credentials_sent', true);
    }

    public function test_collect_payment_response_includes_credentials_meta(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'CollectMeta',
            'email' => 'collect-meta@test.com',
            'phone' => '9999999931',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.credentials_sent', true)
            ->assertJsonPath('data.credentials_email', 'collect-meta@test.com')
            ->assertJsonPath('data.portal_ready', true);
    }

    public function test_portal_credentials_email_sent_when_notify_email_disabled(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'NoNotify',
            'email' => 'no-notify@test.com',
            'phone' => '9999999933',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
            'notify_email' => false,
            'notify_whatsapp' => false,
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.credentials_sent', true)
            ->assertJsonPath('data.credentials_email', 'no-notify@test.com')
            ->assertJsonPath('data.portal_ready', true);
    }

    public function test_portal_credentials_sent_when_user_email_already_exists(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $orphanUser = User::create([
            'name' => 'Old Portal User',
            'email' => 'existing-portal@test.com',
            'password' => bcrypt('old-password'),
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $orphanUser->assignRole('student');

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Existing',
            'email' => 'existing-portal@test.com',
            'phone' => '9999999934',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.credentials_sent', true)
            ->assertJsonPath('data.credentials_email', 'existing-portal@test.com')
            ->assertJsonPath('data.portal_ready', true);

        $student = Student::where('email', 'existing-portal@test.com')->first();
        $this->assertSame($orphanUser->id, $student->user_id);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('old-password', $orphanUser->fresh()->password));
    }

    public function test_resend_portal_credentials_requires_mail_configuration(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'NoMail',
            'email' => 'no-mail@test.com',
            'phone' => '9999999935',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $create->assertCreated();

        $student = Student::where('email', 'no-mail@test.com')->first();

        $this->postJson("/api/v1/students/{$student->id}/resend-portal-credentials")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_resend_portal_credentials_blocked_without_payment(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'UnpaidPortal',
            'email' => 'unpaid-portal@test.com',
            'phone' => '9999999932',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
        ]);
        $create->assertCreated();

        $student = Student::where('email', 'unpaid-portal@test.com')->first();
        if (! $student) {
            $admission = \App\Models\Admission::where('email', 'unpaid-portal@test.com')->first();
            $student = Student::create([
                'student_code' => 'SP9999998',
                'verify_token' => 'TESTTOKEN2',
                'name' => 'UnpaidPortal',
                'email' => 'unpaid-portal@test.com',
                'phone' => '9999999932',
                'branch_id' => $branch->id,
                'plan_name' => $plan->name,
                'status' => 'pending',
                'admission_id' => $admission->id,
                'valid_from' => now()->toDateString(),
                'expiry' => now()->addMonth()->toDateString(),
            ]);
            $admission->update(['student_id' => $student->id]);
        }

        $this->postJson("/api/v1/students/{$student->id}/resend-portal-credentials")
            ->assertStatus(422);
    }
}
