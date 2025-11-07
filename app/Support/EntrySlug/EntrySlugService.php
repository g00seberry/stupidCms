<?php

namespace App\Support\EntrySlug;

use App\Models\Entry;

interface EntrySlugService
{
    /**
     * Создать текущую запись истории (после создания Entry).
     */
    public function onCreated(Entry $entry): void;

    /**
     * Синхронизировать историю при изменении slug.
     * Возвращает true, если slug сменился.
     *
     * @param Entry $entry
     * @param string $oldSlug
     * @param bool $dispatchEvent Диспатчить событие EntrySlugChanged (по умолчанию true)
     * @return bool
     */
    public function onUpdated(Entry $entry, string $oldSlug, bool $dispatchEvent = true): bool;

    /**
     * Получить текущий слуг для Entry.
     */
    public function currentSlug(int $entryId): ?string;
}

