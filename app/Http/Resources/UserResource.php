<?php

namespace App\Http\Resources;

use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->sort()->values()),
            'role' => $this->whenLoaded('roles', fn () => Roles::primary($this->roles->pluck('name')->all())),
            'permissions' => $this->whenLoaded(
                'roles',
                fn () => $this->getAllPermissions()->pluck('name')->sort()->values()
            ),
            'direct_permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name')->sort()->values()
            ),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
