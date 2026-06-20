<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
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
            ->assertJsonPath('data.portal_ready', true);
    }

    public function test_collect_payment_response_includes_credentials_meta(): void
    {
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
            ->assertJsonPath('data.credentials_email', 'collect-meta@test.com')
            ->assertJsonPath('data.portal_ready', true);
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
