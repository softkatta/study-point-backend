<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@studypoint.in',
            'password' => 'demo1234',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@studypoint.in',
            'password' => 'demo1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'admin@studypoint.in']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@studypoint.in',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_student_can_login_with_student_code(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'student@studypoint.in',
            'password' => 'demo1234',
            'status' => 'active',
        ]);
        $user->assignRole('student');

        \App\Models\Student::create([
            'user_id' => $user->id,
            'student_code' => 'SP2024001',
            'verify_token' => 'TESTTOKEN',
            'name' => 'Test Student',
            'email' => 'student@studypoint.in',
            'phone' => '+91 9876543210',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'SP2024001',
            'password' => 'demo1234',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }
}
