<?php

declare(strict_types=1);

namespace App\Domain\Search;

interface SearchClientInterface
{
    /**
     * Выполнить поисковый запрос.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function search(string $indexAlias, array $body): array;

    /**
     * Создать индекс с указанными настройками и маппингами.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $mappings
     */
    public function createIndex(string $indexName, array $settings, array $mappings): void;

    public function deleteIndex(string $indexName): void;

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    public function updateAliases(array $actions): void;

    /**
     * Возвращает список индексов, привязанных к алиасу.
     *
     * @return list<string>
     */
    public function getIndicesForAlias(string $alias): array;

    /**
     * Выполнить bulk-операции.
     *
     * @param list<array<string, mixed>> $operations
     */
    public function bulk(array $operations): void;

    public function refresh(string $indexName): void;
}


