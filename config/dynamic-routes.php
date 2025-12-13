<?php

declare(strict_types=1);

/**
 * Конфигурация для системы динамических маршрутов (DB-driven routing).
 *
 * Определяет политику безопасности для динамических маршрутов:
 * - Разрешённые middleware и контроллеры
 * - Запрещённые префиксы URI
 * - Настройки кэширования
 *
 * @package Config
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Middleware
    |--------------------------------------------------------------------------
    |
    | Массив разрешённых middleware-алиасов, которые могут быть назначены
    | динамическим маршрутам через поле `middleware` в route_nodes.
    |
    | Параметризованные middleware поддерживаются через паттерны:
    | - 'can:*' - разрешает все middleware вида can:action,Model
    | - 'throttle:*' - разрешает все middleware вида throttle:60,1
    | - 'App\Http\Middleware\*' - разрешает все middleware из этого namespace
    |
    | Примеры:
    | - 'web' - стандартный web middleware (CSRF, сессии)
    | - 'api' - стандартный api middleware (stateless, без CSRF)
    | - 'auth' - требует аутентификации
    | - 'jwt.auth' - JWT аутентификация
    | - 'jwt.auth.optional' - опциональная JWT аутентификация
    | - 'no-cache-auth' - запрет кэширования для auth endpoints
    | - 'can:view,Entry' - проверка прав доступа
    | - 'throttle:60,1' - rate limiting
    |
    */
    'allowed_middleware' => [
        'web',
        'api',
        'auth',
        'guest',
        'verified',
        'can:*', // Параметризованный: can:action,Model
        'throttle:*', // Параметризованный: throttle:maxAttempts,decayMinutes
        // JWT middleware
        'jwt.auth',
        'jwt.auth.optional',
        // Cache control middleware
        'no-cache-auth',
        // Custom middleware classes (wildcard для всех middleware из App\Http\Middleware)
        'App\\Http\\Middleware\\*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Controllers
    |--------------------------------------------------------------------------
    |
    | Массив разрешённых контроллеров (полные namespace + класс),
    | которые могут быть использованы в поле `action` для action_type='controller'.
    |
    | Поддерживаются форматы:
    | - Controller@method: 'App\Http\Controllers\BlogController@show'
    | - Invokable controller: 'App\Http\Controllers\HomeController'
    |
    | Можно указать конкретные контроллеры или использовать wildcard:
    | - 'App\Http\Controllers\*' - разрешает все контроллеры из этого namespace
    |
    */
    'allowed_controllers' => [
        'App\Http\Controllers\*', // Разрешает все контроллеры из стандартного namespace
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved Prefixes
    |--------------------------------------------------------------------------
    |
    | Массив запрещённых префиксов URI, которые нельзя использовать
    | в динамических маршрутах. Эти префиксы зарезервированы для системных маршрутов.
    |
    | Примеры:
    | - 'api' - защищает /api/* от перехвата
    | - 'admin' - защищает /admin/* от перехвата
    | - 'sanctum' - защищает /sanctum/* от перехвата
    |
    */
    'reserved_prefixes' => [
        'api',
        'admin',
        'sanctum',
        '_ignition', // Laravel Ignition (debug)
        'horizon', // Laravel Horizon (если используется)
        'telescope', // Laravel Telescope (если используется)
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Время жизни кэша дерева маршрутов в секундах.
    | По умолчанию: 3600 секунд (1 час).
    |
    | Кэш автоматически инвалидируется при изменении route_nodes
    | через RouteNodeObserver.
    |
    */
    'cache_ttl' => env('DYNAMIC_ROUTES_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Префикс для ключей кэша дерева маршрутов.
    | Используется для версионирования и инвалидации кэша.
    |
    | Формат ключа: {prefix}:tree:v{version}
    |
    */
    'cache_key_prefix' => env('DYNAMIC_ROUTES_CACHE_PREFIX', 'dynamic_routes'),
];

