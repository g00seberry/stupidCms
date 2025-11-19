<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * Разрешенные origins для CORS запросов.
     * 
     * Может быть задано через env переменную CORS_ALLOWED_ORIGINS (через запятую).
     * По умолчанию включает localhost:5173 для разработки с Vite.
     * 
     * Важно: при supports_credentials=true нельзя использовать '*', 
     * нужно явно указывать origins.
     */
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')))
    ),

    /*
     * Паттерны для разрешенных origins (для локальной разработки).
     * 
     * Позволяет использовать localhost и 127.0.0.1 с любым портом,
     * что удобно при работе с Vite dev server на разных портах.
     */
    'allowed_origins_patterns' => [
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    /*
     * Разрешить отправку credentials (cookies, authorization headers) в CORS запросах.
     * 
     * При true:
     * - Браузер будет отправлять cookies при кросс-доменных запросах
     * - В ответе будет установлен заголовок Access-Control-Allow-Credentials: true
     * - allowed_origins не может содержать '*' (нужно явно указывать origins)
     */
    'supports_credentials' => true,
];

