<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для загрузки декларативных маршрутов из файлов.
 *
 * Загружает массивы конфигурации RouteNode из файлов в routes/
 * и преобразует их в коллекцию RouteNode объектов (в памяти, без сохранения в БД).
 *
 * @package App\Services\DynamicRoutes
 */
class DeclarativeRouteLoader
{
    /**
     * Загрузить декларативные маршруты из файла.
     *
     * @param string $file Путь к файлу относительно routes/
     * @return array<int, array<string, mixed>> Массив конфигурации маршрутов
     */
    public function loadFromFile(string $file): array
    {
        $path = base_path("routes/{$file}");

        if (!file_exists($path)) {
            Log::warning("Declarative routes file not found: {$file}");
            return [];
        }

        $config = require $path;

        if (!is_array($config)) {
            Log::warning("Declarative routes file must return an array: {$file}");
            return [];
        }

        return $config;
    }

    /**
     * Преобразовать массив конфигурации в коллекцию RouteNode.
     *
     * Создаёт RouteNode объекты в памяти (без сохранения в БД).
     * Обрабатывает иерархию (группы и вложенные маршруты).
     * Фильтрует маршруты по условиям (например, environment).
     * sort_order должен быть указан в файлах routes/*.php.
     *
     * @param array<int, array<string, mixed>> $config Массив конфигурации
     * @param string $source Источник маршрутов (для логирования)
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> Коллекция RouteNode
     */
    public function convertToRouteNodes(array $config, string $source = 'declarative'): Collection
    {
        $nodes = new Collection();

        foreach ($config as $item) {
            if (!$this->shouldLoadRoute($item)) {
                continue;
            }
            
            $node = $this->createFromArray($item, null, $source);
            if ($node) {
                $nodes->push($node);
            }
        }

        return $nodes;
    }

    /**
     * Создать RouteNode из массива конфигурации.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param \App\Models\RouteNode|null $parent Родительский узел
     * @param string $source Источник маршрута (для метаданных)
     * @return \App\Models\RouteNode|null Созданный RouteNode или null при ошибке
     */
    public function createFromArray(
        array $data,
        ?RouteNode $parent = null,
        string $source = 'declarative'
    ): ?RouteNode {
        try {
            // Валидация обязательных полей
            if (!isset($data['kind'])) {
                Log::warning('Declarative route: missing kind', ['data' => $data]);
                return null;
            }

            // Преобразование kind в enum
            $kind = is_string($data['kind']) 
                ? RouteNodeKind::from($data['kind']) 
                : $data['kind'];

            if (!($kind instanceof RouteNodeKind)) {
                Log::warning('Declarative route: invalid kind', ['kind' => $data['kind']]);
                return null;
            }

            // Создание RouteNode (в памяти, без сохранения)
            // Используем отрицательные ID для декларативных маршрутов (чтобы не конфликтовать с БД)
            static $declarativeIdCounter = -1;
            $node = new RouteNode();
            $node->id = $declarativeIdCounter--;
            $node->kind = $kind;
            $node->parent_id = $parent?->id;
            $node->enabled = $data['enabled'] ?? true;
            $node->sort_order = $data['sort_order'] ?? 0;
            $node->readonly = true; // Все декларативные маршруты защищены от изменения
            
            // Временное логирование для диагностики
            if ($kind === RouteNodeKind::GROUP && $parent === null) {
                \Illuminate\Support\Facades\Log::debug('Declarative route: создана корневая группа', [
                    'source' => $source,
                    'sort_order' => $node->sort_order,
                    'prefix' => $node->prefix ?? 'none',
                    'has_children' => isset($data['children']) && is_array($data['children']),
                ]);
            }

            // Для группы
            if ($kind === RouteNodeKind::GROUP) {
                $node->prefix = $data['prefix'] ?? null;
                $node->domain = $data['domain'] ?? null;
                $node->namespace = $data['namespace'] ?? null;
                $node->middleware = $data['middleware'] ?? null;
                $node->where = $data['where'] ?? null;
            }

            // Для маршрута
            if ($kind === RouteNodeKind::ROUTE) {
                $node->uri = $data['uri'] ?? null;
                $node->methods = $data['methods'] ?? null;
                $node->name = $data['name'] ?? null;
                $node->domain = $data['domain'] ?? null;
                $node->middleware = $data['middleware'] ?? null;
                $node->where = $data['where'] ?? null;
                $node->defaults = $data['defaults'] ?? null;

                // Преобразование action_type в enum
                if (isset($data['action_type'])) {
                    $actionType = is_string($data['action_type'])
                        ? RouteNodeActionType::from($data['action_type'])
                        : $data['action_type'];

                    if ($actionType instanceof RouteNodeActionType) {
                        $node->action_type = $actionType;
                        $node->action = $data['action'] ?? null;
                        $node->entry_id = $data['entry_id'] ?? null;
                    }
                } else {
                    // По умолчанию CONTROLLER
                    $node->action_type = RouteNodeActionType::CONTROLLER;
                    $node->action = $data['action'] ?? null;
                }
            }

            // Обработка дочерних узлов (для групп)
            if ($kind === RouteNodeKind::GROUP && isset($data['children']) && is_array($data['children'])) {
                $children = new Collection();
                foreach ($data['children'] as $childData) {
                    if (!$this->shouldLoadRoute($childData)) {
                        continue;
                    }

                    // sort_order для дочерних узлов должен быть указан в файле
                    // Если не указан, используем значение по умолчанию из createFromArray
                    $child = $this->createFromArray($childData, $node, $source);
                    if ($child) {
                        $children->push($child);
                    }
                }
                $node->setRelation('children', $children);
            }

            return $node;
        } catch (\Throwable $e) {
            Log::error('Declarative route: error creating RouteNode', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Проверить, нужно ли загружать маршрут.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @return bool true если маршрут нужно загрузить, false иначе
     */
    private function shouldLoadRoute(array $data): bool
    {
        return true;
    }

    /**
     * Загрузить все декларативные маршруты.
     *
     * Загружает маршруты из всех файлов в routes/.
     * Порядок регистрации определяется sort_order, указанным в файлах:
     * - web_core.php: sort_order = -1000
     * - api.php: sort_order = -999
     * - api_admin.php: sort_order = -998
     * - web_content.php: sort_order = -997
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> Коллекция всех RouteNode
     */
    public function loadAll(): Collection
    {
        $allNodes = new Collection();

        $files = [
            'web_core.php',
            'api.php',
            'api_admin.php',
            'web_content.php',
        ];

        foreach ($files as $file) {
            $config = $this->loadFromFile($file);
            $nodes = $this->convertToRouteNodes($config, $file);
            $allNodes = $allNodes->merge($nodes);
        }

        return $allNodes;
    }
}

