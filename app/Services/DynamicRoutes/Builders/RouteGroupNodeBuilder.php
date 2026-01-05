<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Билдер для создания узлов типа GROUP.
 *
 * Отвечает за создание и настройку групп маршрутов:
 * - Установка полей группы (prefix, domain, namespace, middleware, where)
 * - Рекурсивная обработка дочерних узлов
 *
 * @package App\Services\DynamicRoutes\Builders
 */
class RouteGroupNodeBuilder extends AbstractRouteNodeBuilder
{
    /**
     * Callback для создания дочерних узлов.
     *
     * Используется для рекурсивного создания дочерних узлов,
     * избегая циклической зависимости с DeclarativeRouteLoader.
     *
     * @var callable|null
     */
    private $childNodeBuilder = null;

    /**
     * Установить callback для создания дочерних узлов.
     *
     * @param callable(array<string, mixed>, RouteNode, string): ?RouteNode $builder
     * @return void
     */
    public function setChildNodeBuilder(callable $builder): void
    {
        $this->childNodeBuilder = $builder;
    }

    /**
     * Проверить, поддерживает ли билдер указанный тип узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return bool true если kind === GROUP, false иначе
     */
    public function supports(RouteNodeKind $kind): bool
    {
        return $kind === RouteNodeKind::GROUP;
    }

    /**
     * Построить специфичные поля узла типа GROUP.
     *
     * Устанавливает поля, специфичные для групп:
     * - prefix
     * - domain
     * - namespace
     * - middleware
     * - where
     * - children (рекурсивно)
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @param string $source Источник маршрута (для логирования)
     * @return void
     */
    protected function buildSpecificFields(RouteNode $node, array $data, string $source): void
    {
        // Устанавливаем поля группы
        $this->buildGroupFields($node, $data);

        // Логирование для корневых групп
        if ($node->parent_id === null) {
            Log::debug('Declarative route: создана корневая группа', [
                'source' => $source,
                'sort_order' => $node->sort_order,
                'prefix' => $node->prefix ?? 'none',
                'has_children' => isset($data['children']) && is_array($data['children']),
            ]);
        }

        // Обрабатываем дочерние узлы
        $this->buildChildren($node, $data, $source);
    }

    /**
     * Установить поля группы маршрутов.
     *
     * Устанавливает поля, специфичные для групп:
     * - prefix: префикс URI для всех дочерних маршрутов
     * - domain: домен для группы
     * - namespace: namespace контроллеров
     * - middleware: массив middleware
     * - where: ограничения параметров маршрута
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @return void
     */
    protected function buildGroupFields(RouteNode $node, array $data): void
    {
        $node->prefix = $data['prefix'] ?? null;
        $node->domain = $data['domain'] ?? null;
        $node->namespace = $data['namespace'] ?? null;
        $node->middleware = $data['middleware'] ?? null;
        $node->where = $data['where'] ?? null;
    }

    /**
     * Построить дочерние узлы группы.
     *
     * Рекурсивно создаёт дочерние узлы из массива children.
     * Использует callback для создания узлов, чтобы избежать циклической зависимости.
     *
     * @param \App\Models\RouteNode $node Родительский узел
     * @param array<string, mixed> $data Данные конфигурации
     * @param string $source Источник маршрута (для логирования)
     * @return void
     */
    protected function buildChildren(RouteNode $node, array $data, string $source): void
    {
        if (!isset($data['children']) || !is_array($data['children'])) {
            return;
        }

        if ($this->childNodeBuilder === null) {
            Log::warning('Declarative route: childNodeBuilder not set, skipping children', [
                'node_id' => $node->id,
                'source' => $source,
            ]);
            return;
        }

        $children = new Collection();

        foreach ($data['children'] as $childData) {
            $child = ($this->childNodeBuilder)($childData, $node, $source);
            if ($child !== null) {
                $children->push($child);
            }
        }

        $node->setRelation('children', $children);
    }
}

