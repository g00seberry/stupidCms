<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum для типов действий маршрутов (RouteNode).
 *
 * Определяет два типа действий:
 * - CONTROLLER: универсальный тип для контроллеров, view и redirect
 * - ENTRY: жёсткое назначение конкретной Entry на URL
 *
 * @package App\Enums
 */
enum RouteNodeActionType: string
{
    /**
     * Универсальный тип для контроллеров, view и redirect.
     *
     * Поддерживает следующие форматы в поле `action`:
     * - Controller@method: `App\Http\Controllers\BlogController@show`
     * - Invokable controller: `App\Http\Controllers\HomeController`
     * - View: `view:pages.about`
     * - Redirect: `redirect:/new-page:301` или `redirect:/new-page` (по умолчанию 302)
     *
     * Использование:
     * - Кастомная логика, API endpoints, сложная обработка запросов
     * - Статические страницы без логики (view)
     * - Редиректы старых URL (redirect)
     */
    case CONTROLLER = 'controller';

    /**
     * Жёсткое назначение конкретной Entry на URL.
     *
     * Требует `entry_id` в узле.
     * Использование: статические страницы контента (О компании, Политика конфиденциальности, лендинги).
     * Контроллер: `EntryPageController@show` (автоматически назначается регистратором).
     *
     * Примечание: Динамическое разрешение Entry по slug (например, для блогов)
     * реализуется через `CONTROLLER` с кастомным контроллером, использующим `Entry::wherePath()`.
     */
    case ENTRY = 'entry';

    /**
     * Получить все возможные значения enum.
     *
     * @return array<string> Массив строковых значений: ['controller', 'entry']
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Проверить, требует ли тип действия наличие Entry.
     *
     * @return bool true если требуется entry_id, false иначе
     */
    public function requiresEntry(): bool
    {
        return $this === self::ENTRY;
    }
}

