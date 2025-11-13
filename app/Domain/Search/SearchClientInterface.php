<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * Интерфейс клиента поискового движка.
 *
 * Определяет контракт для работы с поисковым движком (Elasticsearch и т.д.):
 * выполнение запросов, управление индексами, bulk-операции.
 *
 * @package App\Domain\Search
 */
interface SearchClientInterface
{
    /**
     * Выполнить поисковый запрос.
     *
     * @param string $indexAlias Алиас индекса для поиска
     * @param array<string, mixed> $body Тело запроса (Elasticsearch query DSL)
     * @return array<string, mixed> Ответ поискового движка
     */
    public function search(string $indexAlias, array $body): array;

    /**
     * Создать индекс с указанными настройками и маппингами.
     *
     * @param string $indexName Имя индекса
     * @param array<string, mixed> $settings Настройки индекса (shards, replicas и т.д.)
     * @param array<string, mixed> $mappings Маппинги полей (типы, анализаторы)
     * @return void
     */
    public function createIndex(string $indexName, array $settings, array $mappings): void;

    /**
     * Удалить индекс.
     *
     * @param string $indexName Имя индекса для удаления
     * @return void
     */
    public function deleteIndex(string $indexName): void;

    /**
     * Обновить алиасы индексов.
     *
     * Позволяет атомарно переключать алиасы между индексами
     * (например, при реиндексации).
     *
     * @param array<int, array<string, mixed>> $actions Массив действий (add, remove)
     * @return void
     */
    public function updateAliases(array $actions): void;

    /**
     * Возвращает список индексов, привязанных к алиасу.
     *
     * @param string $alias Алиас индекса
     * @return list<string> Список имён индексов
     */
    public function getIndicesForAlias(string $alias): array;

    /**
     * Выполнить bulk-операции.
     *
     * Массовые операции для индексации/удаления документов.
     *
     * @param list<array<string, mixed>> $operations Массив операций (index, delete и т.д.)
     * @return void
     */
    public function bulk(array $operations): void;

    /**
     * Обновить индекс (сделать изменения видимыми для поиска).
     *
     * @param string $indexName Имя индекса
     * @return void
     */
    public function refresh(string $indexName): void;
}


