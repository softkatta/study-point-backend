<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedApplication();
    }

    public function test_public_can_submit_admission(): void
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $response = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'email' => 'rahul@test.com',
            'phone' => '+91 9876543210',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'documents_uploaded' => true,
            'source' => 'online',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending');
        $admissionId = $response->json('data.id');
        $this->assertDatabaseHas('admissions', [
            'email' => 'rahul@test.com',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'admission_id' => $admissionId,
            'status' => 'pending',
        ]);
    }

    public function test_online_admission_with_payment_mode_stays_pending(): void
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $response = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Sachin',
            'email' => 'softkatta@gmail.com',
            'phone' => '1111111114',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'amount' => 200,
            'payment_mode' => 'upi',
            'source' => 'online',
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', 'pending');
        $this->assertDatabaseHas('admissions', [
            'email' => 'softkatta@gmail.com',
            'payment_mode' => 'upi',
            'payment_status' => 'pending',
        ]);
    }

    public function test_full_admission_flow_creates_student_and_subscription(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Priya',
            'last_name' => 'Test',
            'email' => 'priya@test.com',
            'phone' => '+91 9876543211',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('students', ['email' => 'priya@test.com']);
        $this->assertDatabaseHas('subscriptions', ['plan_name' => 'Monthly Pass']);
    }

    public function test_cannot_approve_without_payment(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Unpaid',
            'email' => 'unpaid@test.com',
            'phone' => '+91 9876543213',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'source' => 'online',
        ]);
        $admissionId = $create->json('data.id');

        $this->patchJson("/api/v1/admissions/{$admissionId}/approve")->assertStatus(422);
    }

    public function test_unpaid_manual_approve_reconciles_to_pending_on_list(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $admission = Admission::create([
            'admission_code' => 'ADM888',
            'source' => 'online',
            'status' => AdmissionStatus::Active,
            'payment_status' => 'pending',
            'first_name' => 'AAA',
            'name' => 'AAA',
            'email' => 'aaa@test.com',
            'phone' => '2222222222',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'start_date' => now()->toDateString(),
            'duration_months' => 1,
            'amount' => 200,
        ]);

        $student = \App\Models\Student::create([
            'student_code' => 'SP9999999',
            'verify_token' => 'TESTTOKEN1',
            'name' => 'AAA',
            'email' => 'aaa@test.com',
            'phone' => '2222222222',
            'branch_id' => $branch->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'admission_id' => $admission->id,
            'valid_from' => now()->toDateString(),
            'expiry' => now()->addMonth()->toDateString(),
        ]);

        $admission->update(['student_id' => $student->id]);

        \App\Models\Payment::create([
            'payment_code' => 'PAY-TEST-888',
            'admission_id' => $admission->id,
            'amount' => 200,
            'method' => 'Cash',
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/admissions')->assertOk();

        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'status' => 'pending']);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'pending']);
    }

    public function test_cannot_approve_after_rejection(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $admission = Admission::create([
            'admission_code' => 'ADM999',
            'source' => 'online',
            'status' => AdmissionStatus::Rejected,
            'first_name' => 'Test',
            'name' => 'Test User',
            'email' => 'test@fail.com',
            'phone' => '9999999999',
            'start_date' => now()->toDateString(),
            'duration_months' => 1,
            'amount' => 1499,
        ]);

        $response = $this->patchJson("/api/v1/admissions/{$admission->id}/approve");
        $response->assertStatus(422);
    }
}
