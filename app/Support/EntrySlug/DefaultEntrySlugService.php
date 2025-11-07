<?php

namespace App\Support\EntrySlug;

use App\Events\EntrySlugChanged;
use App\Models\Entry;
use App\Models\EntrySlug;
use Illuminate\Support\Facades\DB;

final class DefaultEntrySlugService implements EntrySlugService
{
    /**
     * Создать текущую запись истории (после создания Entry).
     */
    public function onCreated(Entry $entry): void
    {
        if (empty($entry->slug)) {
            return;
        }

        DB::transaction(function () use ($entry) {
            // Блокируем строки истории для этой entry (защита от гонок)
            EntrySlug::where('entry_id', $entry->id)
                ->lockForUpdate()
                ->get();

            // Гарантируем наличие строки для slug без изменения created_at
            EntrySlug::firstOrCreate(
                [
                    'entry_id' => $entry->id,
                    'slug' => $entry->slug,
                ],
                [
                    'is_current' => true, // Временно, будет установлено массовым UPDATE
                    'created_at' => now(),
                ]
            );

            // Ровно один current — одним атомарным UPDATE
            DB::statement(
                'UPDATE entry_slugs SET is_current = CASE WHEN slug = ? THEN 1 ELSE 0 END WHERE entry_id = ?',
                [$entry->slug, $entry->id]
            );
        });
    }

    /**
     * Синхронизировать историю при изменении slug.
     * Возвращает true, если slug сменился.
     *
     * @param Entry $entry
     * @param string $oldSlug
     * @param bool $dispatchEvent Диспатчить событие EntrySlugChanged (по умолчанию true)
     * @return bool
     */
    public function onUpdated(Entry $entry, string $oldSlug, bool $dispatchEvent = true): bool
    {
        if ($oldSlug === $entry->slug) {
            return false;
        }

        if (empty($entry->slug)) {
            return false;
        }

        DB::transaction(function () use ($entry, $oldSlug) {
            // Блокируем строки истории для этой entry (защита от гонок)
            EntrySlug::where('entry_id', $entry->id)
                ->lockForUpdate()
                ->get();

            // Создаём запись для нового slug, если её нет (сохраняем историческую дату)
            $entrySlug = EntrySlug::firstOrCreate(
                [
                    'entry_id' => $entry->id,
                    'slug' => $entry->slug,
                ],
                [
                    'is_current' => false, // Временно false, будет установлено массовым UPDATE
                    'created_at' => now(),
                ]
            );

            // Массовый UPDATE: устанавливаем is_current в зависимости от slug
            // Это атомарно гарантирует, что только один slug будет is_current=1
            // Выполняем после firstOrCreate, чтобы новая запись тоже попала под UPDATE
            DB::statement(
                "UPDATE entry_slugs SET is_current = CASE WHEN slug = ? THEN 1 ELSE 0 END WHERE entry_id = ?",
                [$entry->slug, $entry->id]
            );
        });

        // Диспатчим событие о смене slug (если разрешено)
        if ($dispatchEvent) {
            EntrySlugChanged::dispatch($entry->id, $oldSlug, $entry->slug);
        }

        return true;
    }

    /**
     * Получить текущий слуг для Entry.
     */
    public function currentSlug(int $entryId): ?string
    {
        $entrySlug = EntrySlug::where('entry_id', $entryId)
            ->where('is_current', true)
            ->first();

        return $entrySlug?->slug;
    }
}

