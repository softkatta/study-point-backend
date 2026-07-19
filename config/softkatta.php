<?php

return [
    'company_api_url' => rtrim(env('SOFTKATTA_COMPANY_API_URL', ''), '/'),
    'public_api_key' => env('SOFTKATTA_PUBLIC_API_KEY', ''),
    'api_secret' => env('SOFTKATTA_API_SECRET', ''),
    'product_slug' => env('SOFTKATTA_PRODUCT_SLUG', 'study-point-management-software'),
    'product_version' => env('SOFTKATTA_PRODUCT_VERSION', env('APP_VERSION', '1.0.0')),
    'offline_grace_days' => (int) env('SOFTKATTA_OFFLINE_GRACE_DAYS', 1),
    'verify_interval_hours' => (int) env('SOFTKATTA_VERIFY_INTERVAL_HOURS', 0),
    'verify_interval_minutes' => (int) env('SOFTKATTA_VERIFY_INTERVAL_MINUTES', 0),
    // Parallel public GETs share one SoftKatta verify for this many seconds (avoids 429→Invalid).
    'verify_coalesce_seconds' => (int) env('SOFTKATTA_VERIFY_COALESCE_SECONDS', 5),
    'timestamp_skew_seconds' => (int) env('SOFTKATTA_TIMESTAMP_SKEW', 300),
    'require_https' => (bool) env('SOFTKATTA_REQUIRE_HTTPS', true),
    'enabled' => (bool) env('SOFTKATTA_LICENSING_ENABLED', true),
    // Public site host SoftKatta Admin should match (SPA). Falls back to FRONTEND_URL.
    'frontend_url' => env('SOFTKATTA_FRONTEND_URL', env('FRONTEND_URL', '')),
    'bound_domain' => env('SOFTKATTA_BOUND_DOMAIN', ''),

    'license_exempt_paths' => [
        'api/v1/install/*',
        'up',
        'api/v1/license/entitlements',
        'api/v1/license/activate',
        'api/v1/license/company-api',
        'api/v1/webhooks/*',
    ],

    'license_public_get_paths' => [
        'api/v1/branches*',
        'api/v1/facilities',
        'api/v1/faqs',
        'api/v1/testimonials',
        'api/v1/homepage/*',
        'api/v1/topbar',
        'api/v1/head-office',
        'api/v1/plans',
        'api/v1/appearance',
        'api/v1/platform/config',
        'api/v1/security/config',
        'api/v1/payment/checkout-config',
        'api/v1/notification/channels',
    ],
];
