<?php

declare(strict_types=1);

namespace App\Domain\Entries;

use App\Models\Entry;

/**
 * Интерфейс сервиса для управления историей slug'ов записей.
 *
 * Определяет контракт для отслеживания изменений slug'ов Entry
 * и сохранения истории в таблице entry_slugs.
 *
 * @package App\Domain\Entries
 */
interface EntrySlugService
{
    /**
     * Создать текущую запись истории после создания Entry.
     *
     * Если slug пуст, операция не выполняется.
     * Использует транзакцию для атомарности.
     *
     * @param \App\Models\Entry $entry Созданная запись
     * @return void
     */
    public function onCreated(Entry $entry): void;

    /**
     * Синхронизировать историю при изменении slug.
     *
     * Создаёт новую запись в истории, если slug изменился.
     * Атомарно обновляет флаг is_current для всех записей истории.
     *
     * @param \App\Models\Entry $entry Обновлённая запись
     * @param string $oldSlug Предыдущий slug
     * @param bool $dispatchEvent Диспатчить событие EntrySlugChanged (по умолчанию true)
     * @return bool true, если slug изменился; false, если остался прежним
     */
    public function onUpdated(Entry $entry, string $oldSlug, bool $dispatchEvent = true): bool;

    /**
     * Получить текущий slug для Entry.
     *
     * @param int $entryId ID записи
     * @return string|null Текущий slug или null, если не найден
     */
    public function currentSlug(int $entryId): ?string;
}

