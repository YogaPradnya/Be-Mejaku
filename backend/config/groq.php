<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Groq API Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi Groq AI yang digunakan oleh chatbot MejaKu.
    |
    */

    'api_key' => env('GROQ_API_KEY'),

    'model' => env('GROQ_MODEL', 'qwen/qwen3.8-27b'),

    'api_url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'),

    'max_tokens' => env('GROQ_MAX_TOKENS', 1024),

    'temperature' => env('GROQ_TEMPERATURE', 0.7),

];
