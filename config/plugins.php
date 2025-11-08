<?php

declare(strict_types=1);

return [
    'path' => base_path('plugins'),
    'manifest' => ['plugin.json', 'composer.json'],
    'auto_route_cache' => env('PLUGINS_AUTO_ROUTE_CACHE', env('APP_ENV') === 'production'),
];

