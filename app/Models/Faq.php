<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question', 'answer', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('id', $value)->first();
    }
}
