<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_and_approve_expense(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();

        $create = $this->postJson('/api/v1/expenses', [
            'title' => 'Electricity Bill',
            'amount' => 5000,
            'category' => 'utilities',
            'branch_id' => $branch->id,
            'expense_date' => '2024-06-10',
        ]);

        $create->assertCreated()->assertJsonPath('data.status', 'pending');
        $expenseId = $create->json('data.id');

        $this->patchJson("/api/v1/expenses/{$expenseId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'status' => 'approved']);
    }

    public function test_branch_manager_expense_is_scoped_to_branch(): void
    {
        $branch = Branch::first();
        $manager = User::factory()->create([
            'email' => 'manager@test.in',
            'status' => 'active',
            'branch_id' => $branch->id,
        ]);
        $manager->assignRole('branch_manager');

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/expenses', [
            'title' => 'Branch Supplies',
            'amount' => 1200,
            'category' => 'supplies',
            'expense_date' => '2024-06-11',
        ])->assertCreated();

        $expense = Expense::latest()->first();
        $this->assertSame((int) $manager->branch_id, (int) $expense->branch_id);
    }
}
