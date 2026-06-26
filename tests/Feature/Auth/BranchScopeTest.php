<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_staff_only_sees_students_from_assigned_branch(): void
    {
        $branches = Branch::orderBy('id')->take(2)->get();
        $this->assertCount(2, $branches);

        [$branchA, $branchB] = $branches;

        $staff = User::create([
            'name' => 'Branch Staff',
            'email' => 'staff-scope@test.com',
            'password' => 'password123',
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);
        $staff->assignRole('staff');

        Student::create([
            'student_code' => 'SP-SCOPE-A',
            'verify_token' => 'SCOPEA',
            'name' => 'Student A',
            'email' => 'student-a@test.com',
            'phone' => '9000000001',
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);

        Student::create([
            'student_code' => 'SP-SCOPE-B',
            'verify_token' => 'SCOPEB',
            'name' => 'Student B',
            'email' => 'student-b@test.com',
            'phone' => '9000000002',
            'branch_id' => $branchB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/students');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Student A', $names);
        $this->assertNotContains('Student B', $names);
    }

    public function test_staff_cannot_view_student_from_another_branch(): void
    {
        $branchA = Branch::orderBy('id')->first();
        $branchB = Branch::orderByDesc('id')->first();
        $this->assertNotSame($branchA->id, $branchB->id);

        $staff = User::create([
            'name' => 'Branch Staff Two',
            'email' => 'staff-scope2@test.com',
            'password' => 'password123',
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);
        $staff->assignRole('receptionist');

        $otherStudent = Student::create([
            'student_code' => 'SP-SCOPE-OTHER',
            'verify_token' => 'SCOPEOTHER',
            'name' => 'Other Branch Student',
            'email' => 'other-branch@test.com',
            'phone' => '9000000003',
            'branch_id' => $branchB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/students/{$otherStudent->id}")
            ->assertForbidden();
    }

    public function test_staff_only_sees_own_branch_in_branch_list(): void
    {
        $branchA = Branch::orderBy('id')->first();

        $staff = User::create([
            'name' => 'Branch Staff Three',
            'email' => 'staff-scope3@test.com',
            'password' => 'password123',
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);
        $staff->assignRole('attendance_operator');

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/branches/manage');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([(int) $branchA->id], $ids);
    }
}
