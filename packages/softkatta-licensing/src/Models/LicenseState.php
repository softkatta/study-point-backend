<?php

namespace SoftKatta\Licensing\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseState extends Model
{
    protected $fillable = [
        'install_token',
        'refresh_token',
        'installation_id',
        'customer_id',
        'product_slug',
        'server_fingerprint',
        'bound_domain',
        'license_key',
        'plan_slug',
        'last_verified_at',
        'installed_at',
        'modules_cache',
        'limits_cache',
        'last_error_code',
        'product_version_at_verify',
    ];

    protected $hidden = [
        'install_token',
        'refresh_token',
        'license_key',
    ];

    protected function casts(): array
    {
        return [
            'install_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'license_key' => 'encrypted',
            'last_verified_at' => 'datetime',
            'installed_at' => 'datetime',
            'modules_cache' => 'array',
            'limits_cache' => 'array',
            'customer_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        $state = static::query()->first();
        if ($state) {
            return $state;
        }

        return static::query()->create([]);
    }
}
