<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_require_2fa_admins_triggers_setup_for_privileged_user(): void
    {
        Setting::saveSection('security', ['require_2fa_admins' => true]);
        Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'manager@studypoint.in',
            'password' => 'Demo1234!',
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('branch_manager');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@studypoint.in',
            'password' => 'Demo1234!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_2fa_setup', true)
            ->assertJsonPath('data.user.email', 'manager@studypoint.in')
            ->assertJsonStructure(['data' => ['setup_token']]);
    }

    public function test_expired_session_token_is_rejected_by_session_timeout_middleware(): void
    {
        Setting::saveSection('security', ['session_timeout_minutes' => 30]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $newToken = $user->createToken('api-token');
        $token = $newToken->accessToken;
        Cache::put("token_activity:{$token->id}", now()->subMinutes(31)->timestamp, now()->addHour());

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('errors.reason', 'timeout');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->id,
        ]);
    }
}
