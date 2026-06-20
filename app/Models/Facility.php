<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = [
        'slug', 'title', 'short_description', 'description', 'bullet_points',
        'icon', 'sort_order', 'show_in_nav', 'show_on_home', 'show_on_page', 'status',
    ];

    protected function casts(): array
    {
        return [
            'bullet_points' => 'array',
            'show_in_nav' => 'boolean',
            'show_on_home' => 'boolean',
            'show_on_page' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('slug', $value)
            ->orWhere('id', $value)
            ->first();
    }
}
