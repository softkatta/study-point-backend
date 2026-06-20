<?php



namespace Tests\Feature\Admission;



use App\Models\Admission;

use App\Models\Payment;

use App\Models\Plan;

use App\Models\Student;

use App\Models\Subscription;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Laravel\Sanctum\Sanctum;

use Tests\TestCase;



class SubscriptionDeleteTest extends TestCase

{

    use RefreshDatabase;



    protected function setUp(): void

    {

        parent::setUp();

        $this->seed();

    }



    public function test_super_admin_can_delete_unpaid_subscription(): void

    {

        $admin = User::where('email', 'admin@studypoint.in')->first();

        Sanctum::actingAs($admin);



        $branch = \App\Models\Branch::first();

        $plan = Plan::where('slug', 'monthly')->first();



        $student = Student::create([

            'student_code' => 'SP8888888',

            'verify_token' => 'SUBDELTOKEN',

            'name' => 'Sub Delete Test',

            'email' => 'sub-delete@test.com',

            'phone' => '7777777777',

            'branch_id' => $branch->id,

            'plan_name' => $plan->name,

            'status' => 'pending',

        ]);



        $subscription = Subscription::create([

            'subscription_code' => 'SUB-TEST-DEL',

            'student_id' => $student->id,

            'plan_id' => $plan->id,

            'branch_id' => $branch->id,

            'plan_name' => $plan->name,

            'start_date' => now()->toDateString(),

            'end_date' => now()->toDateString(),

            'status' => 'pending',

            'membership_source' => 'new',

            'amount' => 200,

        ]);



        Payment::create([

            'payment_code' => 'PAY-SUB-PEND',

            'student_id' => $student->id,

            'subscription_id' => $subscription->id,

            'amount' => 200,

            'method' => 'Pending',

            'status' => 'pending',

        ]);



        $response = $this->deleteJson("/api/v1/subscriptions/{$subscription->id}");



        $response->assertOk();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);

    }



    public function test_cannot_delete_subscription_after_payment_collected(): void

    {

        $admin = User::where('email', 'admin@studypoint.in')->first();

        Sanctum::actingAs($admin);



        $branch = \App\Models\Branch::first();

        $plan = Plan::where('slug', 'monthly')->first();



        $student = Student::create([

            'student_code' => 'SP8888889',

            'verify_token' => 'SUBDELTOKEN2',

            'name' => 'Sub Delete Paid',

            'email' => 'sub-delete-paid@test.com',

            'phone' => '7777777778',

            'branch_id' => $branch->id,

            'plan_name' => $plan->name,

            'status' => 'active',

            'valid_from' => now()->toDateString(),

            'expiry' => now()->addMonth()->toDateString(),

        ]);



        $subscription = Subscription::create([

            'subscription_code' => 'SUB-TEST-DEL2',

            'student_id' => $student->id,

            'plan_id' => $plan->id,

            'branch_id' => $branch->id,

            'plan_name' => $plan->name,

            'start_date' => now()->toDateString(),

            'end_date' => now()->addMonth()->toDateString(),

            'status' => 'active',

            'membership_source' => 'new',

            'amount' => 200,

        ]);



        Payment::create([

            'payment_code' => 'PAY-SUB-PAID',

            'student_id' => $student->id,

            'subscription_id' => $subscription->id,

            'amount' => 200,

            'method' => 'Cash',

            'status' => 'paid',

            'paid_at' => now(),

        ]);



        $response = $this->deleteJson("/api/v1/subscriptions/{$subscription->id}");



        $response->assertStatus(422);

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);

    }

}


