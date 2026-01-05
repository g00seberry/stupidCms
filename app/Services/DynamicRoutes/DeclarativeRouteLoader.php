<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\Builders\AbstractRouteNodeBuilder;
use App\Services\DynamicRoutes\Builders\RouteGroupNodeBuilder;
use App\Services\DynamicRoutes\Builders\RouteNodeBuilderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для загрузки декларативных маршрутов из файлов.
 *
 * Загружает массивы конфигурации RouteNode из файлов в routes/
 * и преобразует их в коллекцию RouteNode объектов (в памяти, без сохранения в БД).
 * Использует билдеры для создания узлов разных типов.
 *
 * @package App\Services\DynamicRoutes
 */
class DeclarativeRouteLoader
{
    /**
     * Фабрика для создания билдеров узлов.
     *
     * @var \App\Services\DynamicRoutes\Builders\RouteNodeBuilderFactory
     */
    private RouteNodeBuilderFactory $builderFactory;

    /**
     * Конструктор.
     *
     * @param \App\Services\DynamicRoutes\Builders\RouteNodeBuilderFactory|null $builderFactory Фабрика билдеров
     */
    public function __construct(?RouteNodeBuilderFactory $builderFactory = null)
    {
        $this->builderFactory = $builderFactory ?? RouteNodeBuilderFactory::createDefault();
        $this->setupChildNodeBuilders();
    }

    /**
     * Настроить callback для создания дочерних узлов в билдерах групп.
     *
     * Устанавливает callback в RouteGroupNodeBuilder для рекурсивного создания дочерних узлов.
     *
     * @return void
     */
    private function setupChildNodeBuilders(): void
    {
        $groupBuilder = $this->builderFactory->create(RouteNodeKind::GROUP);
        if ($groupBuilder instanceof RouteGroupNodeBuilder) {
            $groupBuilder->setChildNodeBuilder(function (array $childData, ?RouteNode $parent, string $source): ?RouteNode {
                return $this->createFromArray($childData, $parent, $source);
            });
        }
    }
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
     * Использует фабрику билдеров для создания узла соответствующего типа.
     * Делегирует всю логику создания билдерам, что упрощает код и улучшает расширяемость.
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
            // Валидация обязательного поля kind
            if (!isset($data['kind'])) {
                Log::warning('Declarative route: missing kind', [
                    'data' => $data,
                    'source' => $source,
                ]);
                return null;
            }

            // Нормализация kind в enum (используем статический метод из AbstractRouteNodeBuilder)
            $kind = AbstractRouteNodeBuilder::normalizeKind($data['kind']);
            if ($kind === null) {
                return null;
            }

            // Получаем билдер для типа узла
            $builder = $this->builderFactory->create($kind);
            if ($builder === null) {
                Log::error('Declarative route: builder not found', [
                    'kind' => $kind->value,
                    'source' => $source,
                ]);
                return null;
            }

            // Создаём узел через билдер
            return $builder->build($data, $parent, $source);
        } catch (\Throwable $e) {
            Log::error('Declarative route: error creating RouteNode', [
                'error' => $e->getMessage(),
                'data' => $data,
                'source' => $source,
                'parent_id' => $parent?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
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

