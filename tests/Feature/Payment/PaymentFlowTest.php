<?php

namespace Tests\Feature\Payment;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedApplication();
    }

    public function test_admin_can_collect_cash_for_admission(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Cash',
            'last_name' => 'Test',
            'email' => 'cash@test.com',
            'phone' => '9999999901',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'amount' => 1499,
            'payment_mode' => 'cash',
            'source' => 'branch',
        ]);
        $create->assertCreated();
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'transaction_id' => 'CASH-001',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('data.payment.status', 'paid');
        $this->assertDatabaseHas('admissions', [
            'id' => $admissionId,
            'payment_status' => 'paid',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('students', ['email' => 'cash@test.com', 'status' => 'active']);
        $this->assertDatabaseHas('payments', [
            'admission_id' => $admissionId,
            'status' => 'paid',
        ]);
    }

    public function test_demo_online_payment_confirm_is_rejected(): void
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Online',
            'email' => 'online@test.com',
            'phone' => '9999999902',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'amount' => 1499,
            'payment_mode' => 'upi',
            'source' => 'online',
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/confirm-payment", [
            'demo' => true,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('admissions', [
            'id' => $admissionId,
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseMissing('students', ['email' => 'online@test.com']);
    }

    public function test_online_payment_cannot_be_manually_verified(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Approve',
            'email' => 'approve-pay@test.com',
            'phone' => '9999999903',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'source' => 'online',
        ]);
        $admissionId = $create->json('data.id');

        $this->patchJson("/api/v1/admissions/{$admissionId}/approve")->assertStatus(422);

        $payment = Payment::where('admission_id', $admissionId)->first();
        $this->patchJson("/api/v1/payments/{$payment->id}/verify")->assertStatus(422);
    }

    public function test_counter_collect_auto_approves_and_activates(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'CashApprove',
            'email' => 'cash-approve@test.com',
            'phone' => '9999999911',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');

        $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseHas('admissions', [
            'id' => $admissionId,
            'status' => 'active',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('students', [
            'email' => 'cash-approve@test.com',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'plan_name' => 'Monthly Pass',
            'status' => 'active',
        ]);
    }

    public function test_admin_paid_admission_create_auto_approves(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'PaidCreate',
            'email' => 'paid-create@test.com',
            'phone' => '9999999912',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'transaction_id' => 'CASH-INIT',
            'source' => 'admin',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('students', [
            'email' => 'paid-create@test.com',
            'status' => 'active',
        ]);
    }

    public function test_renewal_counter_collect_auto_activates(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Renew',
            'email' => 'renew-flow@test.com',
            'phone' => '9999999913',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);
        $admissionId = $create->json('data.id');
        $this->assertDatabaseHas('admissions', ['id' => $admissionId, 'status' => 'active']);

        $student = \App\Models\Student::where('email', 'renew-flow@test.com')->first();
        $subscription = \App\Models\Subscription::where('student_id', $student->id)->first();
        $originalEnd = $subscription->end_date->toDateString();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/renew")->assertOk();

        $subscription->refresh();
        $this->assertSame('pending', $subscription->status->value);
        $this->assertSame($originalEnd, $subscription->end_date->toDateString());

        $payment = Payment::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame('renew', $payment->subscription_action);

        $this->postJson("/api/v1/payments/{$payment->id}/collect", [
            'method' => 'Cash',
            'payment_date' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.renewal_activated', true);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
        $subscription->refresh();
        $this->assertTrue($subscription->end_date->toDateString() > $originalEnd);
    }

    public function test_counter_collect_rejects_online_method(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Online',
            'email' => 'block-upi@test.com',
            'phone' => '9999999910',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'upi',
            'source' => 'online',
        ]);
        $admissionId = $create->json('data.id');

        $response = $this->postJson("/api/v1/admissions/{$admissionId}/collect-payment", [
            'method' => 'UPI',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }
}
