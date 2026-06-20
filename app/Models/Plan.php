<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'category', 'duration_days', 'duration_months',
        'price', 'status', 'description', 'badge', 'is_featured', 'highlights',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'highlights' => 'array',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('slug', $value)
            ->orWhere('id', $value)
            ->first();
    }
}
