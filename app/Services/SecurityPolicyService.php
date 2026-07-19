<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\SecurityDefaults;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

class SecurityPolicyService
{
    public function config(): array
    {
        try {
            return SecurityDefaults::merge(Setting::getSection('security'));
        } catch (\Throwable) {
            // ForceHttps / ApiRateLimit run before EnsureInstalled — never 500 the API
            // when the settings table is briefly unreachable.
            return SecurityDefaults::merge([]);
        }
    }

    public function loginAttemptKey(string $identifier, string $ip): string
    {
        return 'login_attempts:'.sha1(strtolower(trim($identifier)).'|'.$ip);
    }

    public function loginLockKey(string $identifier, string $ip): string
    {
        return 'login_lockout:'.sha1(strtolower(trim($identifier)).'|'.$ip);
    }

    public function isLoginLocked(string $identifier, string $ip): ?int
    {
        $lockKey = $this->loginLockKey($identifier, $ip);
        $expiresAt = Cache::get($lockKey);

        if (! $expiresAt) {
            return null;
        }

        $remaining = (int) $expiresAt - now()->timestamp;

        return $remaining > 0 ? $remaining : null;
    }

    public function recordFailedLogin(string $identifier, string $ip): void
    {
        $config = $this->config();
        $attemptKey = $this->loginAttemptKey($identifier, $ip);
        $attempts = (int) Cache::get($attemptKey, 0) + 1;
        $maxAttempts = (int) ($config['max_login_attempts'] ?? 5);

        Cache::put($attemptKey, $attempts, now()->addHour());

        if ($attempts >= $maxAttempts) {
            $lockMinutes = (int) ($config['lockout_duration_minutes'] ?? 15);
            Cache::put(
                $this->loginLockKey($identifier, $ip),
                now()->addMinutes($lockMinutes)->timestamp,
                now()->addMinutes($lockMinutes),
            );
            Cache::forget($attemptKey);
        }
    }

    public function clearLoginAttempts(string $identifier, string $ip): void
    {
        Cache::forget($this->loginAttemptKey($identifier, $ip));
        Cache::forget($this->loginLockKey($identifier, $ip));
    }

    public function passwordRules(bool $confirmed = true): array
    {
        $config = $this->config();
        $min = (int) ($config['min_password_length'] ?? 8);

        $rules = ['required', 'string', 'min:'.$min];

        if ($config['require_strong_password'] ?? true) {
            $rules[] = 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_\-+=\[\]{}.,:;]).+$/';
        }

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    public function passwordMessages(): array
    {
        $config = $this->config();
        $min = (int) ($config['min_password_length'] ?? 8);
        $messages = [
            'password.min' => "Password must be at least {$min} characters.",
        ];

        if ($config['require_strong_password'] ?? true) {
            $messages['password.regex'] = 'Password must include uppercase, lowercase, number and special character.';
        }

        return $messages;
    }

    public function isPasswordExpired(User $user): bool
    {
        $days = (int) ($this->config()['password_expiry_days'] ?? 0);

        if ($days <= 0 || ! $user->password_changed_at) {
            return false;
        }

        return $user->password_changed_at->addDays($days)->isPast();
    }

    public function revokeOtherSessions(User $user): void
    {
        if (! ($this->config()['single_session_per_user'] ?? false)) {
            return;
        }

        $user->tokens()->delete();
    }

    public function shouldEnforceIpWhitelist(?User $user): bool
    {
        if (! ($this->config()['ip_whitelist_enabled'] ?? false) || ! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'branch_manager', 'staff']);
    }

    public function isIpAllowed(string $ip): bool
    {
        $config = $this->config();
        $raw = (string) ($config['ip_whitelist'] ?? '');

        if ($raw === '') {
            return false;
        }

        $rules = array_filter(array_map('trim', explode(',', $raw)));

        return IpUtils::checkIp($ip, $rules);
    }

    public function apiRateLimit(): int
    {
        return (int) ($this->config()['api_rate_limit_per_minute'] ?? 120);
    }

    public function shouldForceHttps(): bool
    {
        $config = $this->config();

        return (bool) ($config['force_https'] ?? false)
            && ! app()->environment('local', 'testing');
    }

    public function publicConfig(): array
    {
        $config = $this->config();

        return [
            'session_timeout_minutes' => (int) ($config['session_timeout_minutes'] ?? 480),
            'student_self_register' => (bool) ($config['allow_student_self_register'] ?? false),
            'require_2fa_admins' => (bool) ($config['require_2fa_admins'] ?? false),
        ];
    }
}
