<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi CORS untuk mengizinkan FE mengakses API backend MejaKu.
    | Untuk pengembangan lokal, semua origin diizinkan.
    | Untuk produksi, batasi ke domain FE yang spesifik.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'X-Idempotency-Key',
    ],

    'exposed_headers' => [
        'X-Idempotency-Key',
        'X-Demo-Mode',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 86400, // 24 jam

    'supports_credentials' => false,

];
