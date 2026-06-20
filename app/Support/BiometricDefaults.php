<?php

namespace App\Support;

class BiometricDefaults
{
    public static function all(): array
    {
        return [
            'provider' => 'smartoffice',
            'enabled' => false,
            'smartoffice_api_key' => '',
            'smartoffice_server_url' => '',
            'smartoffice_use_proxy' => false,
            'zkteco_server_ip' => '',
            'zkteco_port' => '4370',
            'zkteco_comm_key' => '',
            'essl_server_url' => '',
            'essl_api_key' => '',
            'hikvision_server_url' => '',
            'hikvision_app_key' => '',
            'hikvision_app_secret' => '',
            'custom_api_base_url' => '',
            'custom_api_key' => '',
            'custom_api_auth_header' => 'Authorization',
        ];
    }

    public static function merge(array $stored): array
    {
        return array_merge(self::all(), $stored);
    }

    public static function validationRules(): array
    {
        return [
            'provider' => ['required', 'in:smartoffice,zkteco,essl,hikvision,custom_api,manual'],
            'enabled' => ['nullable', 'boolean'],
            'smartoffice_api_key' => ['nullable', 'string', 'max:500'],
            'smartoffice_server_url' => ['nullable', 'string', 'max:500'],
            'smartoffice_use_proxy' => ['nullable', 'boolean'],
            'zkteco_server_ip' => ['nullable', 'string', 'max:100'],
            'zkteco_port' => ['nullable', 'string', 'max:10'],
            'zkteco_comm_key' => ['nullable', 'string', 'max:500'],
            'essl_server_url' => ['nullable', 'string', 'max:500'],
            'essl_api_key' => ['nullable', 'string', 'max:500'],
            'hikvision_server_url' => ['nullable', 'string', 'max:500'],
            'hikvision_app_key' => ['nullable', 'string', 'max:200'],
            'hikvision_app_secret' => ['nullable', 'string', 'max:500'],
            'custom_api_base_url' => ['nullable', 'string', 'max:500'],
            'custom_api_key' => ['nullable', 'string', 'max:500'],
            'custom_api_auth_header' => ['nullable', 'string', 'max:80'],
        ];
    }
}
