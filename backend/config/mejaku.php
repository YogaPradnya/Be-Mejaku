<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MejaKu Application Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi khusus aplikasi MejaKu seperti biaya layanan, pajak,
    | dan pengaturan lainnya.
    |
    */

    'service_fee' => (int) env('MEJAKU_SERVICE_FEE', 3000),

    'tax_rate' => (float) env('MEJAKU_TAX_RATE', 0.10),

    'payment' => [
        'expiry_minutes' => (int) env('MEJAKU_PAYMENT_EXPIRY_MINUTES', 15),
    ],

    'notes_max_length' => 150,

    'demo_mode' => (bool) env('MEJAKU_DEMO_MODE', true),

];
