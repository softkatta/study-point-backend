<?php

namespace Tests\Feature\Admission;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PlanDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_daily_plan_end_date_matches_start_date(): void
    {
        $plan = Plan::create([
            'slug' => 'daily-test',
            'name' => 'plan 1',
            'category' => 'Daily',
            'duration_days' => 1,
            'duration_months' => 1,
            'price' => 200,
            'status' => 'active',
        ]);

        $start = '2026-06-14';
        $end = PlanDuration::endDateForPlan($start, $plan);

        $this->assertSame('2026-06-14', $end);
    }

    public function test_weekly_plan_end_date_uses_seven_days(): void
    {
        $plan = Plan::create([
            'slug' => 'weekly-test',
            'name' => 'Weekly Test',
            'category' => 'Weekly',
            'duration_days' => 7,
            'duration_months' => 1,
            'price' => 499,
            'status' => 'active',
        ]);

        $end = PlanDuration::endDateForPlan('2026-06-14', $plan);

        $this->assertSame('2026-06-20', $end);
    }

    public function test_daily_admission_creates_same_day_subscription_and_student_expiry(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::create([
            'slug' => 'daily-adm',
            'name' => 'plan 1',
            'category' => 'Daily',
            'duration_days' => 1,
            'duration_months' => 1,
            'price' => 200,
            'status' => 'active',
        ]);

        $start = '2026-06-14';
        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Sachin',
            'last_name' => 'Tawde',
            'email' => 'daily-plan@test.com',
            'phone' => '7887969118',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'start_date' => $start,
            'payment_mode' => 'cash',
            'payment_date' => $start,
            'source' => 'admin',
        ]);

        $create->assertCreated();
        $student = Student::where('email', 'daily-plan@test.com')->firstOrFail();
        $subscription = Subscription::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($start, $student->valid_from->toDateString());
        $this->assertSame($start, $student->expiry->toDateString());
        $this->assertSame($start, $subscription->start_date->toDateString());
        $this->assertSame($start, $subscription->end_date->toDateString());
    }
}
