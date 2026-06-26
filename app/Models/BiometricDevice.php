<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDevice extends Model
{
    protected $fillable = [
        'name', 'serial_number', 'branch_id', 'type', 'status', 'last_sync_at',
    ];

    protected function casts(): array
    {
        return ['last_sync_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
