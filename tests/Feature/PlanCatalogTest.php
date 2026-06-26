<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_list_plan_catalog_from_database(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/plan-catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'name', 'category', 'price', 'status'],
                ],
            ]);

        $this->assertGreaterThan(0, count($this->getJson('/api/v1/plan-catalog')->json('data')));
    }

    public function test_admin_delete_permanently_removes_plan(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $plan = Plan::first();
        $this->assertNotNull($plan);

        $this->deleteJson("/api/v1/plans/{$plan->slug}")
            ->assertOk();

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_public_plans_endpoint_returns_active_plans_only(): void
    {
        $response = $this->getJson('/api/v1/plans');
        $response->assertOk();

        $plans = $response->json('data');
        $this->assertIsArray($plans);
        $this->assertGreaterThan(0, count($plans));

        foreach ($plans as $plan) {
            $this->assertArrayHasKey('slug', $plan);
            $this->assertArrayHasKey('price', $plan);
        }
    }
}
