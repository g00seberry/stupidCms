<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum для типов действий маршрутов (RouteNode).
 *
 * Определяет три типа действий:
 * - CONTROLLER: для контроллеров (Controller@method или invokable)
 * - VIEW: для статических страниц (view с опциональными данными)
 * - REDIRECT: для редиректов (URL с опциональным статусом)
 *
 * @package App\Enums
 */
enum RouteNodeActionType: string
{
    /**
     * Тип для контроллеров.
     *
     * Поддерживает следующие форматы в `action_meta['action']`:
     * - Controller@method: `App\Http\Controllers\BlogController@show`
     * - Invokable controller: `App\Http\Controllers\HomeController`
     *
     * Использование:
     * - Кастомная логика, API endpoints, сложная обработка запросов
     *
     * Формат action_meta:
     * ```php
     * ['action' => 'App\Http\Controllers\BlogController@show']
     * ```
     */
    case CONTROLLER = 'controller';

    /**
     * Тип для статических страниц (view).
     *
     * Использование: статические страницы без логики.
     *
     * Формат action_meta:
     * ```php
     * ['view' => 'pages.about', 'data' => ['key' => 'value']] // data опционально
     * ```
     */
    case VIEW = 'view';

    /**
     * Тип для редиректов.
     *
     * Использование: редиректы старых URL на новые.
     *
     * Формат action_meta:
     * ```php
     * ['to' => '/new-page', 'status' => 301] // status опционально, по умолчанию 302
     * ```
     */
    case REDIRECT = 'redirect';

    /**
     * Получить все возможные значения enum.
     *
     * @return array<string> Массив строковых значений: ['controller', 'view', 'redirect']
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

