<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SecurityPolicyService;
use App\Support\ApiResponse;
use App\Support\BranchScope;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct(
        private SecurityPolicyService $security,
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['branch', 'roles'])->latest();

        BranchScope::apply($query, $request->user(), 'branch_id');

        if ($request->filled('role')) {
            $query->role($request->role);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return ApiResponse::success(
            UserResource::collection($query->paginate($request->integer('per_page', 50)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        $roles = $this->validatedRoles($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => $this->security->passwordRules(false),
            'phone' => ['nullable', 'string', 'max:20'],
            'branch_id' => [
                Rule::requiredIf(fn () => Roles::requiresBranchForRoles($roles)),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], $this->security->passwordMessages());

        $this->assertRolesAssignable($request, $roles);

        if ($branchId = BranchScope::branchId($request->user())) {
            $data['branch_id'] = $branchId;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'branch_id' => Roles::requiresBranchForRoles($roles) ? ($data['branch_id'] ?? null) : null,
            'status' => $data['status'] ?? 'active',
            'password_changed_at' => now(),
        ]);

        $user->syncRoles($roles);
        $this->syncBranchManager($user);

        return ApiResponse::success(new UserResource($user->load(['branch', 'roles'])), 'User created', 201);
    }

    public function show(User $user): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $user);

        return ApiResponse::success(new UserResource($user->load(['branch', 'roles', 'permissions'])));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $user);

        $user->loadMissing('roles');
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return $this->updateProtectedSuperAdmin($request, $user);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if ($request->filled('password')) {
            $password = $request->validate([
                'password' => $this->security->passwordRules(false),
            ], $this->security->passwordMessages())['password'];
            $data['password'] = $password;
            $data['password_changed_at'] = now();
        }

        $scopedBranch = BranchScope::branchId($request->user());
        if ($scopedBranch) {
            unset($data['branch_id']);
        }

        if ($request->has('roles') || $request->filled('role')) {
            $roles = $this->validatedRoles($request);
            $this->assertRolesAssignable($request, $roles);

            if ($scopedBranch && in_array(Roles::SUPER_ADMIN, $roles, true)) {
                return ApiResponse::error('You cannot assign the super admin role.', 403);
            }

            if (Roles::requiresBranchForRoles($roles) && ! ($user->branch_id || $scopedBranch || $request->filled('branch_id'))) {
                return ApiResponse::error('Branch is required for the selected roles.', 422);
            }

            $user->syncRoles($roles);
            $user->syncPermissions([]);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            if (! Roles::requiresBranchForRoles($roles)) {
                $data['branch_id'] = null;
            } elseif ($scopedBranch) {
                $data['branch_id'] = $scopedBranch;
            }
        }

        $user->update($data);
        $this->syncBranchManager($user->fresh());

        return ApiResponse::success(new UserResource($user->fresh(['branch', 'roles'])), 'User updated');
    }

    public function destroy(User $user): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $user);

        if ($user->id === request()->user()?->id) {
            return ApiResponse::error('Cannot delete your own account.', 403);
        }

        $user->loadMissing('roles');
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return ApiResponse::error('Super Admin account cannot be deleted.', 422);
        }

        $user->delete();

        return ApiResponse::success(null, 'User deleted');
    }

    public function resetPassword(User $user): JsonResponse
    {
        if ($this->isProtectedSuperAdmin($user)) {
            return ApiResponse::error('Use Edit User to change the Super Admin password.', 422);
        }

        $user->update(['password' => Hash::make('demo1234')]);

        return ApiResponse::success(null, 'Password reset to demo1234');
    }

    public function changeRole(Request $request, User $user): JsonResponse
    {
        BranchScope::authorizeModel($request->user(), $user);

        if ($this->isProtectedSuperAdmin($user)) {
            return ApiResponse::error('Super Admin roles cannot be changed.', 422);
        }

        $request->validate([
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $roles = $this->validatedRoles($request);
        $this->assertRolesAssignable($request, $roles);

        $scopedBranch = BranchScope::branchId($request->user());
        if ($scopedBranch && in_array(Roles::SUPER_ADMIN, $roles, true)) {
            return ApiResponse::error('You cannot assign the super admin role.', 403);
        }

        if (Roles::requiresBranchForRoles($roles) && ! $user->branch_id && ! $request->filled('branch_id') && ! $scopedBranch) {
            return ApiResponse::error('Branch is required for the selected roles.', 422);
        }

        $user->syncRoles($roles);
        $user->syncPermissions([]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if ($scopedBranch) {
            $user->update(['branch_id' => $scopedBranch]);
        } elseif ($request->filled('branch_id')) {
            $user->update(['branch_id' => $request->integer('branch_id')]);
        } elseif (! Roles::requiresBranchForRoles($roles)) {
            $user->update(['branch_id' => null]);
        }

        $this->syncBranchManager($user->fresh());

        return ApiResponse::success(new UserResource($user->fresh(['branch', 'roles'])), 'Roles updated');
    }

    public function permissions(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions']);

        return ApiResponse::success([
            'roles' => $user->roles->pluck('name')->sort()->values(),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->sort()->values(),
            'direct_permissions' => $user->permissions->pluck('name')->sort()->values(),
            'effective_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ]);
    }

    public function syncPermissions(Request $request, User $user): JsonResponse
    {
        if ($this->isProtectedSuperAdmin($user)) {
            return ApiResponse::error('Super Admin permissions cannot be changed.', 422);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success([
            'direct_permissions' => $user->permissions->pluck('name')->sort()->values(),
            'effective_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ], 'User permissions updated.');
    }

    public function activate(User $user): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $user);

        if ($this->isProtectedSuperAdmin($user)) {
            return ApiResponse::error('Super Admin account status cannot be changed.', 422);
        }

        $user->update(['status' => 'active']);

        return ApiResponse::success(new UserResource($user->fresh(['branch', 'roles'])), 'User activated');
    }

    public function deactivate(User $user): JsonResponse
    {
        BranchScope::authorizeModel(request()->user(), $user);

        if ($this->isProtectedSuperAdmin($user)) {
            return ApiResponse::error('Super Admin account status cannot be changed.', 422);
        }

        $user->update(['status' => 'inactive']);

        return ApiResponse::success(new UserResource($user->fresh(['branch', 'roles'])), 'User deactivated');
    }

    public function activityLogs(User $user): JsonResponse
    {
        return ApiResponse::success($this->audit->recent(50, $user->id));
    }

    /** @return list<string> */
    private function validatedRoles(Request $request): array
    {
        $roles = $request->input('roles');
        if ($roles === null && $request->filled('role')) {
            $roles = [$request->input('role')];
        }

        $validated = validator(
            ['roles' => $roles],
            [
                'roles' => ['required', 'array', 'min:1'],
                'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            ]
        )->validate();

        return array_values(array_unique($validated['roles']));
    }

    /** @param list<string> $roles */
    private function assertRolesAssignable(Request $request, array $roles): void
    {
        foreach ($roles as $role) {
            if ($role === Roles::SUPER_ADMIN && ! $request->user()?->hasRole(Roles::SUPER_ADMIN)) {
                abort(403, 'Only super admins can assign the super admin role.');
            }
        }
    }

    private function syncBranchManager(User $user): void
    {
        $user->loadMissing('roles');
        if (! $user->hasRole(Roles::BRANCH_MANAGER) || ! $user->branch_id) {
            return;
        }

        Branch::where('id', $user->branch_id)->update([
            'manager_name' => $user->name,
            'manager_phone' => $user->phone,
        ]);
    }

    private function isProtectedSuperAdmin(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    private function updateProtectedSuperAdmin(Request $request, User $user): JsonResponse
    {
        if ($request->hasAny(['roles', 'role', 'status', 'branch_id', 'phone'])) {
            return ApiResponse::error('Only name, email, and password can be updated for Super Admin.', 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if ($request->filled('password')) {
            $password = $request->validate([
                'password' => $this->security->passwordRules(false),
            ], $this->security->passwordMessages())['password'];
            $data['password'] = $password;
            $data['password_changed_at'] = now();
        }

        $user->update($data);

        return ApiResponse::success(new UserResource($user->fresh(['branch', 'roles'])), 'User updated');
    }
}
