<?php

declare(strict_types=1);

use App\Enums\RouteNodeKind;

/**
 * Декларативные маршруты для контентных веб-маршрутов.
 *
 * Эти маршруты загружаются автоматически и имеют приоритет над маршрутами из БД.
 * Файл пуст, так как контентные маршруты управляются через БД (route_nodes).
 *
 * @return array<int, array<string, mixed>>
 */
return [
    // Группа для контентных веб-маршрутов
    // Middleware: web
    // sort_order = -997 (четвёртый в порядке регистрации)
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -997,
        'middleware' => ['web'],
        'children' => [
            // Контентные маршруты управляются через БД (route_nodes)
        ],
    ],
];

