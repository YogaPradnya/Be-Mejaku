<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Isi kredensial dari dashboard Midtrans (sandbox atau production)
    | melalui environment variable. Ketika server key kosong, aplikasi
    | otomatis fallback ke mode demo lokal (tanpa memanggil Midtrans).
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY', ''),

    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),

    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),

    'is_sanitized' => (bool) env('MIDTRANS_IS_SANITIZED', true),

    'is_3ds' => (bool) env('MIDTRANS_IS_3DS', true),

];
