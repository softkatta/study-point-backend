<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class TwoFactorService
{
    public function __construct(
        private TotpService $totp,
        private SecurityPolicyService $security,
    ) {}

    public function userRequiresTwoFactor(User $user): bool
    {
        if (! ($this->security->config()['require_2fa_admins'] ?? false)) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'branch_manager', 'staff']);
    }

    public function beginSetup(User $user): array
    {
        $secret = $this->totp->generateSecret();
        $issuer = config('app.name', 'StudyPoint');

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->provisioningUri($issuer, $user->email, $secret),
        ];
    }

    public function confirmSetup(User $user, string $secret, string $code): void
    {
        if (! $this->totp->verify($secret, $code)) {
            throw new RuntimeException('Invalid authenticator code.');
        }

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function disable(User $user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function verifyUserCode(User $user, string $code): bool
    {
        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return false;
        }

        $secret = decrypt($user->two_factor_secret);

        return $this->totp->verify($secret, $code);
    }

    public function createChallenge(User $user): string
    {
        $id = (string) Str::uuid();
        Cache::put("2fa_challenge:{$id}", $user->id, now()->addMinutes(5));

        return $id;
    }

    public function createSetupToken(User $user): string
    {
        $id = (string) Str::uuid();
        Cache::put("2fa_setup:{$id}", $user->id, now()->addMinutes(15));

        return $id;
    }

    public function userIdFromChallenge(string $challengeId): ?int
    {
        return Cache::pull("2fa_challenge:{$challengeId}");
    }

    public function userIdFromSetupToken(string $setupToken): ?int
    {
        $key = "2fa_setup:{$setupToken}";

        return Cache::get($key);
    }

    public function consumeSetupToken(string $setupToken): ?int
    {
        return Cache::pull("2fa_setup:{$setupToken}");
    }
}
