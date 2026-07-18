<?php

namespace Tests\Feature\License;

use SoftKatta\Licensing\Models\LicenseState;
use SoftKatta\Licensing\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallLockAndGraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_status_reports_not_installed_without_lock(): void
    {
        File::delete(storage_path('app/installed'));
        LicenseState::query()->delete();

        config(['softkatta.enabled' => true]);

        $this->getJson('/api/v1/install/status')
            ->assertOk()
            ->assertJsonPath('data.installed', false);
    }

    public function test_installer_locked_after_install(): void
    {
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/installed'), now()->toIso8601String());
        LicenseState::current()->forceFill([
            'installed_at' => now(),
            'install_token' => 'test-install-token',
            'installation_id' => (string) \Illuminate\Support\Str::uuid(),
        ])->save();

        $this->postJson('/api/v1/install/database', [
            'host' => '127.0.0.1',
            'database' => 'x',
            'username' => 'x',
        ])->assertStatus(410)
            ->assertJsonPath('error_code', 'ALREADY_INSTALLED');
    }

    public function test_offline_grace_allows_cached_verification(): void
    {
        $state = LicenseState::current();
        $state->forceFill([
            'install_token' => 'token',
            'refresh_token' => 'refresh',
            'installation_id' => (string) \Illuminate\Support\Str::uuid(),
            'last_verified_at' => now()->subDay(),
            'modules_cache' => ['students' => true],
            'limits_cache' => ['max_users' => 5],
            'installed_at' => now(),
        ])->save();

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/installed'), now()->toIso8601String());

        config([
            'softkatta.enabled' => true,
            'softkatta.company_api_url' => 'http://127.0.0.1:9/api/v1/company',
            'softkatta.public_api_key' => 'x',
            'softkatta.api_secret' => 'y',
            'softkatta.offline_grace_days' => 5,
            'softkatta.verify_interval_hours' => 0,
        ]);

        /** @var LicenseService $service */
        $service = app(LicenseService::class);
        $result = $service->verify(true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['grace'] ?? false);
    }

    public function test_grace_expires(): void
    {
        $state = LicenseState::current();
        $state->forceFill([
            'install_token' => 'token',
            'refresh_token' => 'refresh',
            'installation_id' => (string) \Illuminate\Support\Str::uuid(),
            'last_verified_at' => now()->subDays(10),
            'installed_at' => now(),
        ])->save();

        config([
            'softkatta.enabled' => true,
            'softkatta.company_api_url' => 'http://127.0.0.1:9/api/v1/company',
            'softkatta.public_api_key' => 'x',
            'softkatta.api_secret' => 'y',
            'softkatta.offline_grace_days' => 5,
            'softkatta.verify_interval_hours' => 0,
        ]);

        $result = app(LicenseService::class)->verify(true);

        $this->assertFalse($result['ok']);
        $this->assertSame('GRACE_EXPIRED', $result['error_code']);
    }
}
