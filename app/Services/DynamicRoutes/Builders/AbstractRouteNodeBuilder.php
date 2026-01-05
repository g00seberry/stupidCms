<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use Illuminate\Support\Facades\Log;

/**
 * Абстрактный базовый класс для билдеров узлов маршрутов.
 *
 * Содержит общую логику создания RouteNode:
 * - Генерация отрицательных ID для декларативных маршрутов
 * - Нормализация kind в enum
 * - Установка общих полей (parent_id, enabled, sort_order, readonly)
 *
 * @package App\Services\DynamicRoutes\Builders
 */
abstract class AbstractRouteNodeBuilder implements RouteNodeBuilderInterface
{
    /**
     * Счётчик для генерации отрицательных ID декларативных маршрутов.
     *
     * Начинается с -1 и уменьшается при каждом вызове.
     * Отрицательные ID используются для избежания конфликтов с БД.
     *
     * @var int
     */
    private static int $declarativeIdCounter = -1;

    /**
     * Нормализовать kind в enum RouteNodeKind.
     *
     * Преобразует строковое значение или enum в RouteNodeKind.
     * Статический метод для использования в других классах без создания экземпляра билдера.
     *
     * @param mixed $kind Значение kind (строка или RouteNodeKind)
     * @return \App\Enums\RouteNodeKind|null Нормализованный enum или null при ошибке
     */
    public static function normalizeKind(mixed $kind): ?RouteNodeKind
    {
        if ($kind instanceof RouteNodeKind) {
            return $kind;
        }

        if (is_string($kind)) {
            try {
                return RouteNodeKind::from($kind);
            } catch (\ValueError $e) {
                Log::warning('Declarative route: invalid kind', [
                    'kind' => $kind,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        Log::warning('Declarative route: kind must be string or RouteNodeKind', [
            'kind' => $kind,
            'type' => gettype($kind),
        ]);

        return null;
    }

    /**
     * Нормализовать kind в enum RouteNodeKind (нестатическая версия для использования в дочерних классах).
     *
     * @param mixed $kind Значение kind (строка или RouteNodeKind)
     * @return \App\Enums\RouteNodeKind|null Нормализованный enum или null при ошибке
     */
    protected function normalizeKindInstance(mixed $kind): ?RouteNodeKind
    {
        return self::normalizeKind($kind);
    }

    /**
     * Сгенерировать следующий отрицательный ID для декларативного маршрута.
     *
     * @return int Отрицательный ID (начинается с -1, уменьшается)
     */
    protected function generateDeclarativeId(): int
    {
        return self::$declarativeIdCounter--;
    }

    /**
     * Создать базовый RouteNode с общими полями.
     *
     * Создаёт новый RouteNode и устанавливает общие поля:
     * - id (отрицательный для декларативных)
     * - kind
     * - parent_id
     * - enabled
     * - sort_order
     * - readonly (всегда true для декларативных)
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param \App\Models\RouteNode|null $parent Родительский узел
     * @return \App\Models\RouteNode|null Созданный узел или null при ошибке
     */
    protected function createBaseNode(array $data, ?RouteNode $parent = null): ?RouteNode
    {
        // Валидация обязательного поля kind
        if (!isset($data['kind'])) {
            Log::warning('Declarative route: missing kind', ['data' => $data]);
            return null;
        }

        // Нормализация kind
        $kind = self::normalizeKind($data['kind']);
        if ($kind === null) {
            return null;
        }

        // Проверка, что билдер поддерживает этот тип
        if (!$this->supports($kind)) {
            Log::warning('Declarative route: builder does not support kind', [
                'kind' => $kind->value,
                'builder' => static::class,
            ]);
            return null;
        }

        // Создание узла
        $node = new RouteNode();
        $node->id = $this->generateDeclarativeId();
        $node->kind = $kind;
        $node->parent_id = $parent?->id;
        $node->enabled = $data['enabled'] ?? true;
        $node->sort_order = $data['sort_order'] ?? 0;
        $node->readonly = true; // Все декларативные маршруты защищены от изменения

        return $node;
    }

    /**
     * Установить общие поля узла.
     *
     * Устанавливает поля, которые есть у всех типов узлов.
     * Этот метод вызывается после createBaseNode() для дополнительной настройки.
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @param \App\Models\RouteNode|null $parent Родительский узел
     * @return void
     */
    protected function setCommonFields(RouteNode $node, array $data, ?RouteNode $parent): void
    {
        // Общие поля уже установлены в createBaseNode()
        // Этот метод можно переопределить в дочерних классах для дополнительной логики
    }

    /**
     * Построить RouteNode из массива конфигурации.
     *
     * Реализация по умолчанию: создаёт базовый узел и вызывает специфичные методы.
     * Дочерние классы могут переопределить этот метод для кастомной логики.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param \App\Models\RouteNode|null $parent Родительский узел
     * @param string $source Источник маршрута (для логирования)
     * @return \App\Models\RouteNode|null Созданный RouteNode или null при ошибке
     */
    public function build(array $data, ?RouteNode $parent = null, string $source = 'declarative'): ?RouteNode
    {
        try {
            // Создаём базовый узел
            $node = $this->createBaseNode($data, $parent);
            if ($node === null) {
                return null;
            }

            // Устанавливаем общие поля
            $this->setCommonFields($node, $data, $parent);

            // Вызываем специфичную логику построения
            $this->buildSpecificFields($node, $data, $source);

            return $node;
        } catch (\Throwable $e) {
            Log::error('Declarative route: error building RouteNode', [
                'error' => $e->getMessage(),
                'data' => $data,
                'builder' => static::class,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Построить специфичные поля узла.
     *
     * Этот метод должен быть реализован в дочерних классах
     * для установки полей, специфичных для типа узла (GROUP или ROUTE).
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @param string $source Источник маршрута (для логирования)
     * @return void
     */
    abstract protected function buildSpecificFields(RouteNode $node, array $data, string $source): void;
}

