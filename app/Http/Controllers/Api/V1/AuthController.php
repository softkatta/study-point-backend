<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SecurityPolicyService;
use App\Services\StudentRegistrationService;
use App\Services\TwoFactorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private SecurityPolicyService $security,
        private TwoFactorService $twoFactor,
        private AuditService $audit,
        private StudentRegistrationService $studentRegistration,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = trim($request->email);
        $ip = $request->ip() ?? '0.0.0.0';

        if ($remaining = $this->security->isLoginLocked($identifier, $ip)) {
            $minutes = (int) ceil($remaining / 60);

            return ApiResponse::error("Too many login attempts. Try again in {$minutes} minute(s).", 429);
        }

        $user = User::where('email', $identifier)->first();

        if (! $user) {
            $student = Student::where('student_code', $identifier)->first();
            $user = $student?->user_id ? User::find($student->user_id) : null;
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->security->recordFailedLogin($identifier, $ip);
            $this->audit->log('auth.login_failed', null, 'auth', null, $request, ['identifier' => $identifier]);

            return ApiResponse::error('Invalid credentials.', 401);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('Account is inactive.', 403);
        }

        if ($this->security->isPasswordExpired($user)) {
            return ApiResponse::error('Password expired. Please change your password.', 403, [
                'password_expired' => true,
            ]);
        }

        $this->security->clearLoginAttempts($identifier, $ip);

        if ($this->twoFactor->userRequiresTwoFactor($user)) {
            if (! $user->two_factor_enabled) {
                $setupToken = $this->twoFactor->createSetupToken($user);

                return ApiResponse::success([
                    'requires_2fa_setup' => true,
                    'setup_token' => $setupToken,
                    'user' => new UserResource($user->load(['branch', 'roles'])),
                ], 'Two-factor setup required');
            }

            $challengeId = $this->twoFactor->createChallenge($user);

            return ApiResponse::success([
                'requires_2fa' => true,
                'challenge_id' => $challengeId,
                'user' => new UserResource($user->load(['branch', 'roles'])),
            ], 'Two-factor code required');
        }

        return $this->issueToken($user, $request);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $this->twoFactor->userIdFromChallenge($request->challenge_id);
        if (! $userId) {
            return ApiResponse::error('Challenge expired. Please login again.', 422);
        }

        $user = User::findOrFail($userId);
        if (! $this->twoFactor->verifyUserCode($user, $request->code)) {
            return ApiResponse::error('Invalid authenticator code.', 422);
        }

        return $this->issueToken($user, $request);
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['setup_token' => ['required', 'string']]);

        $userId = $this->twoFactor->userIdFromSetupToken($request->setup_token);
        if (! $userId) {
            return ApiResponse::error('Setup session expired. Please login again.', 422);
        }

        $user = User::findOrFail($userId);
        $setup = $this->twoFactor->beginSetup($user);

        return ApiResponse::success($setup, 'Scan the QR code in Google Authenticator or similar app');
    }

    public function confirmTwoFactorSetup(Request $request): JsonResponse
    {
        $request->validate([
            'setup_token' => ['required', 'string'],
            'secret' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $this->twoFactor->consumeSetupToken($request->setup_token);
        if (! $userId) {
            return ApiResponse::error('Setup session expired. Please login again.', 422);
        }

        $user = User::findOrFail($userId);

        try {
            $this->twoFactor->confirmSetup($user, $request->secret, $request->code);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $this->audit->log('auth.2fa_enabled', $user, 'user', (string) $user->id, $request);

        return $this->issueToken($user->fresh(['branch', 'roles']), $request, 'Two-factor enabled. Login successful');
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        if (! $this->twoFactor->verifyUserCode($user, $request->code)) {
            return ApiResponse::error('Invalid authenticator code.', 422);
        }

        $this->twoFactor->disable($user);
        $this->audit->log('auth.2fa_disabled', $user, 'user', (string) $user->id, $request);

        return ApiResponse::success(null, 'Two-factor authentication disabled');
    }

    public function studentRegister(Request $request): JsonResponse
    {
        if (! $this->studentRegistration->isAllowed()) {
            return ApiResponse::error('Student self-registration is currently disabled.', 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => $this->security->passwordRules(),
            'branch_id' => ['nullable', 'exists:branches,id'],
            'city' => ['nullable', 'string', 'max:100'],
        ], $this->security->passwordMessages());

        try {
            $result = $this->studentRegistration->register($data, $request->ip());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'student_code' => $result['student_code'],
        ], 'Registration successful', 201);
    }

    public function registrationStatus(): JsonResponse
    {
        return ApiResponse::success([
            'enabled' => $this->studentRegistration->isAllowed(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->audit->log('auth.logout', $user, 'auth', null, $request);
        $user->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['branch', 'roles']);

        return ApiResponse::success([
            ...(new UserResource($user))->resolve($request),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'requires_2fa_policy' => $this->twoFactor->userRequiresTwoFactor($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => $this->security->passwordRules(),
        ], $this->security->passwordMessages());

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 422);
        }

        $user->update([
            'password' => $request->password,
            'password_changed_at' => now(),
        ]);

        $this->audit->log('auth.password_changed', $user, 'user', (string) $user->id, $request);

        return ApiResponse::success(null, 'Password changed successfully');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(null, 'Password reset link sent if email exists.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $this->security->passwordRules(),
        ], $this->security->passwordMessages());

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__($status), 422);
        }

        return ApiResponse::success(null, 'Password reset successfully');
    }

    private function issueToken(User $user, Request $request, string $message = 'Login successful'): JsonResponse
    {
        $this->security->revokeOtherSessions($user);
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->audit->log('auth.login', $user, 'auth', (string) $user->id, $request);

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user->load(['branch', 'roles'])),
        ], $message);
    }
}
