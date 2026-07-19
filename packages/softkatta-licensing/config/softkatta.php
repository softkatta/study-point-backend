<?php

return [
    'company_api_url' => rtrim(env('SOFTKATTA_COMPANY_API_URL', 'http://127.0.0.1:8090/api/v1/company'), '/'),
    'public_api_key' => env('SOFTKATTA_PUBLIC_API_KEY', ''),
    'api_secret' => env('SOFTKATTA_API_SECRET', ''),
    'product_slug' => env('SOFTKATTA_PRODUCT_SLUG', 'study-point-management-software'),
    'product_version' => env('SOFTKATTA_PRODUCT_VERSION', env('APP_VERSION', '1.0.0')),
    'offline_grace_days' => (int) env('SOFTKATTA_OFFLINE_GRACE_DAYS', 1),
    // 0 = always re-check SoftKatta (recommended so Suspend takes effect quickly).
    'verify_interval_hours' => (int) env('SOFTKATTA_VERIFY_INTERVAL_HOURS', 0),
    'verify_interval_minutes' => (int) env('SOFTKATTA_VERIFY_INTERVAL_MINUTES', 1),
    'timestamp_skew_seconds' => (int) env('SOFTKATTA_TIMESTAMP_SKEW', 300),
    'require_https' => (bool) env('SOFTKATTA_REQUIRE_HTTPS', true),
    'enabled' => (bool) env('SOFTKATTA_LICENSING_ENABLED', true),

    /*
    | Paths skipped by EnsureLicenseValid (product-specific public reads).
    | Each entry is matched with Request::is().
    */
    'license_exempt_paths' => [
        'api/v1/install/*',
        'up',
        'api/v1/license/entitlements',
        'api/v1/license/activate',
        'api/v1/license/company-api',
        'api/v1/webhooks/*',
    ],

    /*
    | GET paths that skip live license verification (still require installed).
    */
    'license_public_get_paths' => [],
];
