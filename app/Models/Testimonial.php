<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'name', 'role', 'quote', 'rating', 'avatar', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('id', $value)->first();
    }
}
