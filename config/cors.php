<?php

declare(strict_types=1);

$origins = array_values(array_unique(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    array_merge(
        [(string) env('FRONTEND_URL', 'http://localhost:3000')],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ),
))));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-API-Key', 'Accept', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
