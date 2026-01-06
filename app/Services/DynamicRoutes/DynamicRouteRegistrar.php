<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для регистрации динамических маршрутов из БД и декларативных маршрутов из файлов.
 *
 * Загружает общее дерево маршрутов, которое включает декларативные маршруты из routes/
 * и динамические маршруты из route_nodes, регистрирует их в Laravel Router.
 * Декларативные маршруты идут первыми в дереве (имеют приоритет).
 * Поддерживает группы маршрутов, различные типы действий (Controller, View, Redirect),
 * проверку безопасности через DynamicRouteGuard.
 * Декларативные и динамические маршруты объединены в общее дерево через RouteNodeRepository::getEnabledTree().
 *
 * Использует паттерн Strategy для разделения логики регистрации разных типов узлов
 * через RouteNodeRegistrarFactory и ActionResolverFactory.
 *
 * @package App\Services\DynamicRoutes
 */
class DynamicRouteRegistrar
{
    /**
     * @param \App\Repositories\RouteNodeRepository $repository Репозиторий для загрузки дерева маршрутов
     * @param \App\Services\DynamicRoutes\DynamicRouteGuard $guard Guard для проверки безопасности
     * @param \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory $registrarFactory Фабрика для создания регистраторов
     */
    public function __construct(
        private RouteNodeRepository $repository,
        private DynamicRouteGuard $guard,
        private RouteNodeRegistrarFactory $registrarFactory,
    ) {}

    /**
     * Зарегистрировать все маршруты (декларативные и динамические).
     *
     * Загружает общее дерево включённых маршрутов, которое включает
     * декларативные маршруты из routes/ и динамические маршруты из БД.
     * Декларативные маршруты идут первыми в дереве (имеют приоритет).
     *
     * @return void
     */
    public function register(): void
    {
        try {
            $tree = $this->repository->getEnabledTree();

            foreach ($tree as $rootNode) {
                $this->registerNode($rootNode);
            }
        } catch (\Throwable $e) {
            Log::error('Dynamic routes: ошибка при регистрации маршрутов', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Зарегистрировать узел (группу или маршрут).
     *
     * Использует RouteNodeRegistrarFactory для получения нужного регистратора
     * и делегирует регистрацию конкретному регистратору.
     *
     * @param \App\Models\RouteNode $node Узел для регистрации
     * @return void
     */
    private function registerNode(RouteNode $node): void
    {
        if (!$node->enabled) {
            return;
        }

        $registrar = $this->registrarFactory->create($node->kind);
        if ($registrar === null) {
            Log::warning('Dynamic route: регистратор не найден', [
                'route_node_id' => $node->id,
                'kind' => $node->kind?->value,
            ]);
            return;
        }

        $registrar->register($node);
    }
}

