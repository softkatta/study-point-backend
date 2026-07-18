<?php

namespace SoftKatta\Licensing\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use SoftKatta\Licensing\Models\LicenseState;
use SoftKatta\Licensing\Support\LicenseErrorCode;

class LicenseService
{
    public function __construct(
        private CompanyApiClient $client,
        private ServerFingerprintService $fingerprint,
    ) {}

    public function isLicensingEnabled(): bool
    {
        return (bool) config('softkatta.enabled', true);
    }

  public function isInstalled(): bool
  {
        try {
            $state = LicenseState::query()->first();
            $hasToken = filled($state?->install_token);

            if (File::exists(storage_path('app/installed')) && ! $hasToken) {
                // Stale lock from an incomplete install — allow wizard to resume.
                File::delete(storage_path('app/installed'));
            }

            if (! $hasToken) {
                return false;
            }

            if (File::exists(storage_path('app/installed'))) {
                return true;
            }

            return $state !== null && $state->installed_at !== null;
        } catch (\Throwable) {
            // DB down: if the install lock file exists, treat as installed — never reopen the wizard.
            return File::exists(storage_path('app/installed'));
        }
  }

    public function state(): LicenseState
    {
        return LicenseState::current();
    }

    public function activate(string $licenseKey): array
    {
        $state = $this->state();
        $result = $this->client->activate($licenseKey, $state->installation_id);

        if (! ($result['ok'] ?? false)) {
            $state->forceFill(['last_error_code' => $result['error_code'] ?? LicenseErrorCode::INVALID_LICENSE])->save();

            return $result;
        }

        $data = $result['data'];
        $profile = $data['configuration_profile'] ?? [];

        $state->forceFill([
            'install_token' => $data['install_token'],
            'refresh_token' => $data['refresh_token'],
            'installation_id' => $data['installation_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'product_slug' => $data['product_slug'] ?? config('softkatta.product_slug'),
            'server_fingerprint' => $this->fingerprint->generate(),
            'bound_domain' => $data['bound_domain'] ?? $this->fingerprint->currentDomain(),
            'license_key' => $licenseKey,
            'plan_slug' => is_array($profile['plan'] ?? null) ? ($profile['plan']['name'] ?? null) : ($profile['plan'] ?? null),
            'last_verified_at' => now(),
            'modules_cache' => $profile['modules'] ?? [],
            'limits_cache' => $profile['limits'] ?? [],
            'last_error_code' => null,
            'product_version_at_verify' => config('softkatta.product_version'),
        ])->save();

        return $result;
    }

    /**
     * True when SoftKatta has already rejected this install (suspend/revoke/etc.).
     * Public pages must also stop — do not wait for cache expiry.
     * Remotely recoverable codes (suspended) still block until the next successful online check.
     */
    public function isHardBlocked(): bool
    {
        if (! $this->isLicensingEnabled()) {
            return false;
        }

        $code = $this->state()->last_error_code;
        if (! LicenseErrorCode::isHardFailure($code)) {
            return false;
        }

        // Token-dead failures stay blocked until manual/CLI re-activate.
        // Suspend/disable stay blocked for public traffic, but verify/heartbeat may recover online.
        return true;
    }

    /**
     * @return array{ok: bool, error_code?: string, message?: string, data?: mixed, from_cache?: bool}
     */
    public function verify(bool $force = false): array
    {
        if (! $this->isLicensingEnabled()) {
            return ['ok' => true, 'from_cache' => true, 'data' => ['licensing_disabled' => true]];
        }

        $state = $this->state();

        if (! $state->install_token || ! $state->installation_id) {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'message' => 'Product is not activated.',
            ];
        }

        if (LicenseErrorCode::isHardFailure($state->last_error_code) && ! $force) {
            // Suspend/disable can be cleared by SoftKatta Admin Activate — always re-check online.
            if (! LicenseErrorCode::isRemotelyRecoverable($state->last_error_code)) {
                return [
                    'ok' => false,
                    'error_code' => $state->last_error_code,
                    'message' => 'License access is blocked. Contact SoftKatta support or re-activate after the license is restored.',
                ];
            }
        }

        // 0 = always hit SoftKatta (fast Suspend). Default 1 minute for public traffic.
        $intervalMinutes = (int) config('softkatta.verify_interval_minutes', 1);
        $version = (string) config('softkatta.product_version');
        $versionChanged = $state->product_version_at_verify && $state->product_version_at_verify !== $version;

        if (
            ! $force
            && $intervalMinutes > 0
            && ! $versionChanged
            && $state->last_verified_at
            && $state->last_error_code === null
            && $state->last_verified_at->gt(now()->subMinutes($intervalMinutes))
        ) {
            return [
                'ok' => true,
                'from_cache' => true,
                'data' => [
                    'modules' => $state->modules_cache ?? [],
                    'limits' => $state->limits_cache ?? [],
                    'bound_domain' => $state->bound_domain,
                ],
            ];
        }

        $result = $this->client->verify($state->install_token, $state->installation_id);

        if ($result['unavailable'] ?? false) {
            return $this->allowOfflineGrace($state, $result['message'] ?? 'Company API unavailable');
        }

        if (! ($result['ok'] ?? false) && ($result['error_code'] ?? '') === LicenseErrorCode::INVALID_INSTALL_TOKEN) {
            $refreshed = $this->refreshToken();
            if ($refreshed['ok'] ?? false) {
                $state = $this->state();
                $result = $this->client->verify($state->install_token, $state->installation_id);
            } elseif (LicenseErrorCode::isHardFailure($refreshed['error_code'] ?? null)) {
                return $this->recordHardFailure($state, $refreshed);
            }
        }

        if (! ($result['ok'] ?? false)) {
            return $this->recordHardFailure($state, $result);
        }

        $data = $result['data'] ?? [];
        $state->forceFill([
            'last_verified_at' => now(),
            'modules_cache' => $data['modules'] ?? $state->modules_cache,
            'limits_cache' => $data['limits'] ?? $state->limits_cache,
            'bound_domain' => $data['bound_domain'] ?? $state->bound_domain,
            'plan_slug' => $data['plan'] ?? $state->plan_slug,
            'customer_id' => $data['customer_id'] ?? $state->customer_id,
            'last_error_code' => null,
            'product_version_at_verify' => $version,
            'server_fingerprint' => $this->fingerprint->generate(),
        ])->save();

        return $result;
    }

    public function refreshToken(): array
    {
        $state = $this->state();

        if (! $state->refresh_token || ! $state->installation_id) {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'message' => 'No refresh token available.',
            ];
        }

        $result = $this->client->refreshToken($state->refresh_token, $state->installation_id);

        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $data = $result['data'] ?? [];
        $state->forceFill([
            'install_token' => $data['install_token'] ?? $state->install_token,
            'refresh_token' => $data['refresh_token'] ?? $state->refresh_token,
            'installation_id' => $data['installation_id'] ?? $state->installation_id,
            'last_error_code' => null,
        ])->save();

        return $result;
    }

    public function heartbeat(): array
    {
        $state = $this->state();

        if ((! $state->install_token || ! $state->installation_id) && filled($state->license_key)) {
            $reactivated = $this->attemptAutoReactivate($state);
            if ($reactivated['ok'] ?? false) {
                $state = $this->state();
            } else {
                return $reactivated;
            }
        }

        if (! $state->install_token || ! $state->installation_id) {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'message' => 'Product is not activated.',
            ];
        }

        $result = $this->client->heartbeat($state->install_token, $state->installation_id);

        if ($result['unavailable'] ?? false) {
            return $this->allowOfflineGrace($state, $result['message'] ?? 'Company API unavailable');
        }

        if ($result['ok'] ?? false) {
            $data = $result['data'] ?? [];
            $state->forceFill([
                'last_verified_at' => now(),
                'modules_cache' => $data['modules'] ?? $state->modules_cache,
                'limits_cache' => $data['limits'] ?? $state->limits_cache,
                'last_error_code' => null,
            ])->save();

            return $result;
        }

        // Admin Activate after token revoke: SoftKatta is active again — mint new install tokens automatically.
        // Only retry activate when the token is dead — not while SoftKatta still reports SUSPENDED.
        if (
            filled($state->license_key)
            && ($result['error_code'] ?? '') === LicenseErrorCode::INVALID_INSTALL_TOKEN
        ) {
            $reactivated = $this->attemptAutoReactivate($state);
            if ($reactivated['ok'] ?? false) {
                $state = $this->state();
                $retry = $this->client->heartbeat($state->install_token, $state->installation_id);
                if ($retry['ok'] ?? false) {
                    $data = $retry['data'] ?? [];
                    $state->forceFill([
                        'last_verified_at' => now(),
                        'modules_cache' => $data['modules'] ?? $state->modules_cache,
                        'limits_cache' => $data['limits'] ?? $state->limits_cache,
                        'last_error_code' => null,
                    ])->save();

                    return $retry;
                }

                return $reactivated;
            }

            return $this->recordHardFailure($state, $reactivated);
        }

        return $this->recordHardFailure($state, $result);
    }

    /**
     * After SoftKatta Admin Activate, re-issue install tokens using the stored license key.
     *
     * @return array{ok: bool, error_code?: string, message?: string, data?: mixed}
     */
    public function attemptAutoReactivate(?LicenseState $state = null): array
    {
        $state ??= $this->state();
        $key = trim((string) ($state->license_key ?? ''));

        if ($key === '') {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::INVALID_INSTALL_TOKEN,
                'message' => 'No stored license key available for automatic re-activation.',
            ];
        }

        return $this->activate($key);
    }

    public function syncModules(): array
    {
        $state = $this->state();
        $result = $this->client->modules($state->install_token, $state->installation_id);
        if ($result['ok'] ?? false) {
            $state->forceFill([
                'modules_cache' => $result['data']['modules'] ?? $state->modules_cache,
            ])->save();
        }

        return $result;
    }

    public function syncLimits(): array
    {
        $state = $this->state();
        $result = $this->client->limits($state->install_token, $state->installation_id);
        if ($result['ok'] ?? false) {
            $state->forceFill([
                'limits_cache' => $result['data']['limits'] ?? $state->limits_cache,
            ])->save();
        }

        return $result;
    }

    public function configurationProfile(): array
    {
        $state = $this->state();

        return [
            'installation_id' => $state->installation_id,
            'bound_domain' => $state->bound_domain,
            'product_slug' => $state->product_slug ?? config('softkatta.product_slug'),
            'product_version' => config('softkatta.product_version'),
            'plan' => $state->plan_slug,
            'modules' => $state->modules_cache ?? [],
            'limits' => $state->limits_cache ?? [],
            'customer_id' => $state->customer_id,
            'last_verified_at' => optional($state->last_verified_at)?->toIso8601String(),
        ];
    }

    public function entitlements(): array
    {
        $state = $this->state();

        return [
            'installed' => $this->isInstalled(),
            'product_slug' => $state->product_slug ?? config('softkatta.product_slug'),
            'bound_domain' => $state->bound_domain,
            'plan' => $state->plan_slug,
            'modules' => $state->modules_cache ?? [],
            'limits' => $state->limits_cache ?? [],
            'last_verified_at' => optional($state->last_verified_at)?->toIso8601String(),
            'last_error_code' => $state->last_error_code,
        ];
    }

    public function moduleEnabled(string $module): bool
    {
        $modules = $this->state()->modules_cache ?? [];
        if ($modules === []) {
            return true;
        }

        return (bool) ($modules[$module] ?? false);
    }

    public function limit(string $key, ?int $default = null): ?int
    {
        $limits = $this->state()->limits_cache ?? [];
        if (! array_key_exists($key, $limits)) {
            return $default;
        }

        return (int) $limits[$key];
    }

    public function assertWithinLimit(string $key, int $currentCount): void
    {
        $max = $this->limit($key);
        if ($max === null) {
            return;
        }

        if ($currentCount >= $max) {
            throw new RuntimeException("Plan limit reached for {$key} ({$max}).");
        }
    }

    public function markInstalled(): void
    {
        $state = $this->state();
        $state->forceFill(['installed_at' => now()])->save();
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/installed'), now()->toIso8601String());
    }

    private function allowOfflineGrace(LicenseState $state, string $message): array
    {
        // Never use offline grace after SoftKatta already blocked this license.
        if (LicenseErrorCode::isHardFailure($state->last_error_code)) {
            return [
                'ok' => false,
                'error_code' => $state->last_error_code,
                'message' => 'License access is blocked.',
            ];
        }

        $graceDays = (int) config('softkatta.offline_grace_days', 1);

        if (! $state->last_verified_at) {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::COMPANY_API_UNAVAILABLE,
                'message' => $message,
            ];
        }

        if ($state->last_verified_at->lt(now()->subDays(max(0, $graceDays)))) {
            return [
                'ok' => false,
                'error_code' => LicenseErrorCode::GRACE_EXPIRED,
                'message' => 'Offline grace period expired. Online verification required.',
            ];
        }

        return [
            'ok' => true,
            'from_cache' => true,
            'grace' => true,
            'message' => $message,
            'data' => [
                'modules' => $state->modules_cache ?? [],
                'limits' => $state->limits_cache ?? [],
            ],
        ];
    }

    /**
     * @param  array{ok?: bool, error_code?: string, message?: string, data?: mixed}  $result
     * @return array{ok: bool, error_code: string, message: string, data?: mixed}
     */
    private function recordHardFailure(LicenseState $state, array $result): array
    {
        $code = $result['error_code'] ?? LicenseErrorCode::INVALID_LICENSE;
        $message = $result['message'] ?? 'License verification failed.';

        $payload = [
            'last_error_code' => $code,
        ];

        if (LicenseErrorCode::isHardFailure($code)) {
            // Drop positive cache so the next request cannot treat the license as OK.
            $payload['last_verified_at'] = null;
        }

        $state->forceFill($payload)->save();

        return [
            'ok' => false,
            'error_code' => $code,
            'message' => $message,
            'data' => $result['data'] ?? null,
        ];
    }
}
