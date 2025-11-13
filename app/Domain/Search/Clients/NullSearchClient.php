<?php

declare(strict_types=1);

namespace App\Domain\Search\Clients;

use App\Domain\Search\SearchClientInterface;

/**
 * Null-реализация SearchClientInterface.
 *
 * Используется когда поиск отключен. Все методы возвращают пустые результаты
 * или выполняют no-op операции.
 *
 * @package App\Domain\Search\Clients
 */
final class NullSearchClient implements SearchClientInterface
{
    /**
     * Выполнить поисковый запрос (no-op).
     *
     * @param string $indexAlias Алиас индекса (игнорируется)
     * @param array<string, mixed> $body Тело запроса (игнорируется)
     * @return array<string, mixed> Пустой результат поиска
     */
    public function search(string $indexAlias, array $body): array
    {
        return [
            'took' => 0,
            'hits' => [
                'total' => ['value' => 0, 'relation' => 'eq'],
                'hits' => [],
            ],
        ];
    }

    /**
     * Создать индекс (no-op).
     *
     * @param string $indexName Имя индекса (игнорируется)
     * @param array<string, mixed> $settings Настройки (игнорируются)
     * @param array<string, mixed> $mappings Маппинги (игнорируются)
     * @return void
     */
    public function createIndex(string $indexName, array $settings, array $mappings): void
    {
        // no-op
    }

    /**
     * Удалить индекс (no-op).
     *
     * @param string $indexName Имя индекса (игнорируется)
     * @return void
     */
    public function deleteIndex(string $indexName): void
    {
        // no-op
    }

    /**
     * Обновить алиасы (no-op).
     *
     * @param array<int, array<string, mixed>> $actions Действия (игнорируются)
     * @return void
     */
    public function updateAliases(array $actions): void
    {
        // no-op
    }

    /**
     * Получить индексы для алиаса (no-op).
     *
     * @param string $alias Алиас (игнорируется)
     * @return list<string> Пустой массив
     */
    public function getIndicesForAlias(string $alias): array
    {
        return [];
    }

    /**
     * Выполнить bulk-операции (no-op).
     *
     * @param list<array<string, mixed>> $operations Операции (игнорируются)
     * @return void
     */
    public function bulk(array $operations): void
    {
        // no-op
    }

    /**
     * Обновить индекс (no-op).
     *
     * @param string $indexName Имя индекса (игнорируется)
     * @return void
     */
    public function refresh(string $indexName): void
    {
        // no-op
    }
}


