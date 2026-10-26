<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Admin Dashboard
    |--------------------------------------------------------------------------
    |
    | Kredensial admin default diambil dari ENV agar tidak hardcode.
    | Isi di .env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
    | Terpakai oleh Database\Seeders\AdminUserSeeder.
    |
    */

    'name' => env('ADMIN_NAME', 'Administrator'),
    'email' => env('ADMIN_EMAIL', 'admin@mejaku.local'),
    'password' => env('ADMIN_PASSWORD', 'admin123'),

];
