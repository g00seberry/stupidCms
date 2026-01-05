<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;

/**
 * Интерфейс для билдеров узлов маршрутов.
 *
 * Определяет контракт для создания RouteNode объектов из массива конфигурации.
 * Каждый билдер отвечает за создание узлов определённого типа (GROUP или ROUTE).
 *
 * @package App\Services\DynamicRoutes\Builders
 */
interface RouteNodeBuilderInterface
{
    /**
     * Построить RouteNode из массива конфигурации.
     *
     * Создаёт и настраивает RouteNode объект на основе переданных данных.
     * Выполняет валидацию, создание базового узла и специфичную конфигурацию.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param \App\Models\RouteNode|null $parent Родительский узел
     * @param string $source Источник маршрута (для логирования)
     * @return \App\Models\RouteNode|null Созданный RouteNode или null при ошибке
     */
    public function build(array $data, ?RouteNode $parent = null, string $source = 'declarative'): ?RouteNode;

    /**
     * Проверить, поддерживает ли билдер указанный тип узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return bool true если билдер поддерживает тип, false иначе
     */
    public function supports(RouteNodeKind $kind): bool;
}

