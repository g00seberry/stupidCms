<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum для типов узлов маршрутов (RouteNode).
 *
 * Определяет два типа узлов:
 * - GROUP: группа маршрутов (для организации иерархии, применения middleware, prefix и т.д.)
 * - ROUTE: конкретный маршрут (HTTP endpoint)
 *
 * @package App\Enums
 */
enum RouteNodeKind: string
{
    /**
     * Группа маршрутов.
     *
     * Используется для организации иерархии маршрутов, применения общих настроек
     * (prefix, domain, namespace, middleware) к дочерним узлам.
     */
    case GROUP = 'group';

    /**
     * Конкретный маршрут.
     *
     * Представляет HTTP endpoint с определённым URI, методами и действием.
     */
    case ROUTE = 'route';

    /**
     * Получить все возможные значения enum.
     *
     * @return array<string> Массив строковых значений: ['group', 'route']
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Проверить, является ли узел группой.
     *
     * @return bool true если узел является группой, false иначе
     */
    public function isGroup(): bool
    {
        return $this === self::GROUP;
    }

    /**
     * Проверить, является ли узел маршрутом.
     *
     * @return bool true если узел является маршрутом, false иначе
     */
    public function isRoute(): bool
    {
        return $this === self::ROUTE;
    }
}

