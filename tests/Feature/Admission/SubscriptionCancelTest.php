<?php

namespace Tests\Feature\Admission;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_cancel_subscription_creates_prorated_refund_pending(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = \App\Models\Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $start = now()->startOfDay();
        $end = $start->copy()->addDays(29);

        $student = Student::create([
            'student_code' => 'SP7777777',
            'verify_token' => 'SUBCANTOK',
            'name' => 'Sub Cancel Test',
            'email' => 'sub-cancel@test.com',
            'phone' => '6666666666',
            'branch_id' => $branch->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'valid_from' => $start->toDateString(),
            'expiry' => $end->toDateString(),
        ]);

        $subscription = Subscription::create([
            'subscription_code' => 'SUB-TEST-CAN',
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'branch_id' => $branch->id,
            'plan_name' => $plan->name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'active',
            'membership_source' => 'new',
            'amount' => 3000,
        ]);

        $paid = Payment::create([
            'payment_code' => 'PAY-SUB-PAID',
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'subscription_action' => 'new',
            'amount' => 3000,
            'method' => 'Cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Payment::create([
            'payment_code' => 'PAY-SUB-PEND',
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'subscription_action' => 'renew',
            'amount' => 3000,
            'method' => 'Pending',
            'status' => 'pending',
        ]);

        Invoice::create([
            'invoice_code' => 'INV-SUB-CAN',
            'student_id' => $student->id,
            'payment_id' => $paid->id,
            'amount' => 3000,
            'gst_amount' => 540,
            'total' => 3540,
            'status' => 'paid',
            'issued_at' => now(),
        ]);

        $response = $this->patchJson("/api/v1/subscriptions/{$subscription->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paid->id,
            'status' => 'refund_pending',
            'refund_amount' => 3000.00,
            'refund_status' => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_code' => 'PAY-SUB-PEND',
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'status' => 'expired',
            'plan_name' => null,
        ]);
        $this->assertDatabaseHas('invoices', [
            'payment_id' => $paid->id,
            'document_type' => 'payment',
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('invoices', [
            'payment_id' => $paid->id,
            'document_type' => 'refund',
            'amount' => 3000.00,
            'status' => 'pending',
        ]);

        $markRefund = $this->postJson("/api/v1/payments/{$paid->id}/refund");
        $markRefund->assertOk();
        $this->assertDatabaseHas('payments', [
            'id' => $paid->id,
            'status' => 'refunded',
            'refund_status' => 'received',
        ]);
        $this->assertDatabaseHas('invoices', [
            'payment_id' => $paid->id,
            'document_type' => 'refund',
            'status' => 'paid',
        ]);
        $this->assertNotNull(Payment::find($paid->id)->refunded_at);
    }

    public function test_cancel_subscription_prorates_refund_for_remaining_days(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = \App\Models\Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $start = now()->startOfDay()->subDays(14);
        $end = now()->startOfDay()->addDays(15);

        $student = Student::create([
            'student_code' => 'SP7777778',
            'verify_token' => 'SUBCANTOK2',
            'name' => 'Sub Cancel Prorate',
            'email' => 'sub-cancel2@test.com',
            'phone' => '6666666667',
            'branch_id' => $branch->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'valid_from' => $start->toDateString(),
            'expiry' => $end->toDateString(),
        ]);

        $subscription = Subscription::create([
            'subscription_code' => 'SUB-TEST-CAN2',
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'branch_id' => $branch->id,
            'plan_name' => $plan->name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'active',
            'membership_source' => 'renew',
            'amount' => 3000,
        ]);

        $paid = Payment::create([
            'payment_code' => 'PAY-SUB-PAID2',
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'amount' => 3000,
            'method' => 'Cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->patchJson("/api/v1/subscriptions/{$subscription->id}/cancel")->assertOk();

        $expectedRefund = round(3000 * ((15 + 1) / (14 + 15 + 1)), 2);

        $this->assertDatabaseHas('payments', [
            'id' => $paid->id,
            'refund_amount' => $expectedRefund,
            'refund_status' => 'pending',
            'status' => 'refund_pending',
        ]);
        $this->assertDatabaseHas('invoices', [
            'payment_id' => $paid->id,
            'document_type' => 'refund',
            'amount' => $expectedRefund,
            'status' => 'pending',
        ]);
    }
}
