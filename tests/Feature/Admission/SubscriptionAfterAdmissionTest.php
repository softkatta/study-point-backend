<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionAfterAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedApplication();
    }

    public function test_approve_creates_portal_user_and_subscription(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Portal',
            'last_name' => 'Student',
            'email' => 'portal-flow@test.com',
            'phone' => '9999999920',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $create->assertCreated()->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('students', ['email' => 'portal-flow@test.com']);
        $student = Student::where('email', 'portal-flow@test.com')->first();
        $this->assertNotNull($student);
        $this->assertNotNull($student->user_id);
        $this->assertDatabaseHas('subscriptions', [
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_unpaid_approve_blocked_then_collect_activates_subscription(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'PayLater',
            'email' => 'pay-later@test.com',
            'phone' => '9999999921',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $this->patchJson("/api/v1/admissions/{$admissionId}/approve")->assertStatus(422);

        $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ])->assertOk();

        $student = Student::where('email', 'pay-later@test.com')->first();
        $subscription = Subscription::where('student_id', $student->id)->first();
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_follow_up_can_be_saved_after_approve(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Follow',
            'email' => 'follow@test.com',
            'phone' => '9999999922',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->putJson("/api/v1/admissions/{$admissionId}", [
            'follow_up_date' => now()->addDays(3)->toDateString(),
            'follow_up_note' => 'Call student about documents',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.follow_up_note', 'Call student about documents');
    }
}
