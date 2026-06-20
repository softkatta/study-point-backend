<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    public function test_student_registration_disabled_by_default(): void
    {
        $response = $this->postJson('/api/v1/auth/student/register', [
            'name' => 'New Student',
            'email' => 'new@studypoint.in',
            'phone' => '+91 9876543210',
            'password' => 'Demo1234!',
            'password_confirmation' => 'Demo1234!',
        ]);

        $response->assertStatus(403);
    }

    public function test_student_can_self_register_when_enabled(): void
    {
        Setting::saveSection('security', ['allow_student_self_register' => true]);

        $response = $this->postJson('/api/v1/auth/student/register', [
            'name' => 'New Student',
            'email' => 'new@studypoint.in',
            'phone' => '+91 9876543210',
            'password' => 'Demo1234!',
            'password_confirmation' => 'Demo1234!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user', 'student_code']]);

        $this->assertDatabaseHas('users', ['email' => 'new@studypoint.in']);
        $this->assertDatabaseHas('students', ['email' => 'new@studypoint.in']);
    }

    public function test_registration_status_reflects_setting(): void
    {
        $this->getJson('/api/v1/auth/student/register/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        Setting::saveSection('security', ['allow_student_self_register' => true]);

        $this->getJson('/api/v1/auth/student/register/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);
    }
}
