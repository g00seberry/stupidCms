<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;

/**
 * Декларативные маршруты для системных веб-маршрутов.
 *
 * Эти маршруты загружаются автоматически и имеют приоритет над маршрутами из БД.
 * Используются для статических системных маршрутов, которые не должны изменяться через UI.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    // Группа для системных веб-маршрутов
    // Middleware: web
    // sort_order = -1000 (первый в порядке регистрации)
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -1000,
        'middleware' => ['web'],
        'children' => [
            // Главная страница
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => 'App\Http\Controllers\HomeController',
                'name' => 'home',
            ],
            // Тестовые маршруты (только для testing окружения)
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/admin/ping',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => 'App\Http\Controllers\AdminPingController@ping',
                'options' => [
                    'environments' => ['testing'],
                ],
            ],
            // Тестовый маршрут для проверки авторизации (только для testing)
            // Используется в тестах, оставлен в старом формате routes/web_core.php
            // так как требует анонимную функцию, которая не может быть сериализована
        ],
    ],
];

