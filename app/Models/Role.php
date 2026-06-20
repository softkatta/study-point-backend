<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'label',
        'guard_name',
        'is_system',
        'requires_branch',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_branch' => 'boolean',
        ];
    }

    public function displayLabel(): string
    {
        return $this->label ?: Str::headline(str_replace('_', ' ', $this->name));
    }
}
