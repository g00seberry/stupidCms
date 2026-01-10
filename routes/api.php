<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;

/**
 * Декларативные маршруты для публичного API.
 *
 * Эти маршруты загружаются автоматически и имеют приоритет над маршрутами из БД.
 * Используются для статических системных маршрутов, которые не должны изменяться через UI.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    // Группа для публичных API маршрутов
    // Полный префикс: api/v1
    // Middleware группа 'api' применяется в RouteServiceProvider через Route::middleware('api')
    // sort_order = -999 (второй в порядке регистрации)
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -999,
        'prefix' => 'api/v1',
        'middleware' => ['api'],
        'children' => [
            // Authentication endpoints
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/login',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'App\Http\Controllers\Auth\LoginController@login',
                ],
                'name' => 'api.auth.login',
                'middleware' => ['throttle:login', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/refresh',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'App\Http\Controllers\Auth\RefreshController@refresh',
                ],
                'name' => 'api.auth.refresh',
                'middleware' => ['throttle:refresh', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/logout',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'App\Http\Controllers\Auth\LogoutController@logout',
                ],
                'name' => 'api.auth.logout',
                'middleware' => ['jwt.auth', 'throttle:login', 'no-cache-auth'],
            ],
            // Public media access
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/media/{id}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'App\Http\Controllers\MediaPreviewController@show',
                ],
                'name' => 'api.v1.media.show',
                'middleware' => ['jwt.auth.optional', 'throttle:api'],
            ],
        ],
    ],
];

