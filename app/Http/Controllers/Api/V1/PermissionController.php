<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return ApiResponse::success([
            'permissions' => $permissions,
            'groups' => Permissions::groups(),
        ]);
    }

    public function roles(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $this->ensureSuperAdminHasAllPermissions();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->orderBy('name')
            ->with('permissions')
            ->get()
            ->map(fn (Role $role) => $this->formatRole($role))
            ->values();

        return ApiResponse::success($roles);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:100'],
            'requires_branch' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'label' => $validated['label'],
            'guard_name' => 'web',
            'is_system' => false,
            'requires_branch' => (bool) ($validated['requires_branch'] ?? false),
        ]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success($this->formatRole($role->fresh('permissions')), 'Role created.', 201);
    }

    public function updateRole(Request $request, string $roleName): JsonResponse
    {
        $this->authorizeAccess($request);

        $role = $this->findRole($roleName);

        $rules = [
            'label' => ['sometimes', 'string', 'max:100'],
            'requires_branch' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];

        if (! $role->is_system) {
            $rules['name'] = [
                'sometimes',
                'string',
                'max:60',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ];
        }

        $validated = $request->validate($rules);

        if ($role->is_system && array_key_exists('name', $validated) && $validated['name'] !== $role->name) {
            return ApiResponse::error('System role slug cannot be changed.', 422);
        }

        $role->fill(collect($validated)->only(['name', 'label', 'requires_branch'])->all());
        $role->save();

        if (array_key_exists('permissions', $validated)) {
            if (Roles::permissionsLocked($role->name)) {
                return ApiResponse::error('Super Admin permissions cannot be modified.', 422);
            }

            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success($this->formatRole($role->fresh('permissions')), 'Role updated.');
    }

    public function destroyRole(Request $request, string $roleName): JsonResponse
    {
        $this->authorizeAccess($request);

        $role = $this->findRole($roleName);

        if ($role->is_system || Roles::isProtected($role->name)) {
            return ApiResponse::error('This role cannot be deleted.', 422);
        }

        $assigned = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->id)
            ->count();

        if ($assigned > 0) {
            return ApiResponse::error('Remove this role from all users before deleting.', 422);
        }

        $role->permissions()->detach();
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success(null, 'Role deleted.');
    }

    public function updateRolePermissions(Request $request, string $roleName): JsonResponse
    {
        $this->authorizeAccess($request);

        $role = $this->findRole($roleName);

        if (Roles::permissionsLocked($role->name)) {
            return ApiResponse::error('Super Admin permissions cannot be modified.', 422);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success($this->formatRole($role->fresh('permissions')), 'Role permissions updated.');
    }

    private function findRole(string $roleName): Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', $roleName)
            ->firstOrFail();
    }

    private function formatRole(Role $role): array
    {
        $role->loadMissing('permissions');

        $locked = Roles::permissionsLocked($role->name);
        $permissions = $locked
            ? collect(Permissions::ALL)->sort()->values()
            : $role->permissions->pluck('name')->sort()->values();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->displayLabel(),
            'is_system' => (bool) $role->is_system,
            'requires_branch' => (bool) $role->requires_branch,
            'permissions_locked' => $locked,
            'permissions' => $permissions,
        ];
    }

    private function ensureSuperAdminHasAllPermissions(): void
    {
        $role = Role::query()->where('guard_name', 'web')->where('name', Roles::SUPER_ADMIN)->first();
        if (! $role) {
            return;
        }

        if ($role->permissions()->count() !== count(Permissions::ALL)) {
            $role->syncPermissions(Permissions::ALL);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole(Roles::SUPER_ADMIN) && ! $user->can(Permissions::PERMISSIONS_MANAGE))) {
            abort(403, 'Unauthorized');
        }
    }
}
