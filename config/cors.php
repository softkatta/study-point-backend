<?php

$localOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$fromEnv = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

$frontend = trim((string) env('FRONTEND_URL', ''));

$origins = array_values(array_unique(array_filter(array_merge(
    $fromEnv,
    $frontend !== '' ? [$frontend] : [],
    env('APP_ENV', 'production') === 'local' ? $localOrigins : [],
))));

if ($origins === []) {
    $origins = $localOrigins;
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ))),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
