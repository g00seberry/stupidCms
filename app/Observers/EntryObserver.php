<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Sanitizer\RichTextSanitizer;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Services\Entry\EntryIndexer;
use App\Support\Slug\Slugifier;
use App\Support\Slug\SlugOptions;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Observer для модели Entry.
 *
 * Обрабатывает события жизненного цикла Entry:
 * - Генерация slug из title (если не указан)
 * - Проверка глобальной уникальности slug (все записи)
 * - Проверка зарезервированных путей
 * - Санитизация HTML полей (body_html, excerpt_html) в data_json
 *
 * @package App\Observers
 */
class EntryObserver
{
    /**
     * @param \App\Support\Slug\Slugifier $slugifier Генератор slug'ов
     * @param \App\Support\Slug\UniqueSlugService $uniqueSlugService Сервис уникальности slug'ов
     * @param \App\Domain\Sanitizer\RichTextSanitizer $sanitizer Санитизатор HTML
     * @param \App\Services\Entry\EntryIndexer $indexer Сервис индексации Entry
     */
    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService,
        private RichTextSanitizer $sanitizer,
        private EntryIndexer $indexer,
    ) {}

    /**
     * Обработать событие "creating" для Entry.
     *
     * Генерирует slug из title (если не указан) и санитизирует HTML поля.
     *
     * @param \App\Models\Entry $entry Создаваемая запись
     * @return void
     */
    public function creating(Entry $entry): void
    {
        $this->ensureSlug($entry);
        $this->sanitizeRichTextFields($entry);
    }

    /**
     * Обработать событие "updating" для Entry.
     *
     * Пересчитывает slug при изменении title или slug.
     * Санитизирует HTML поля при изменении data_json.
     *
     * @param \App\Models\Entry $entry Обновляемая запись
     * @return void
     */
    public function updating(Entry $entry): void
    {
        // Если изменился title или slug, пересчитываем
        if ($entry->isDirty(['title', 'slug'])) {
            $this->ensureSlug($entry);
        }

        // Санитизируем richtext поля при изменении data_json
        if ($entry->isDirty('data_json')) {
            $this->sanitizeRichTextFields($entry);
        }
    }

    /**
     * Handle the Entry "saved" event.
     *
     * Автоматическая индексация Entry при сохранении.
     *
     * @param Entry $entry
     * @return void
     */
    public function saved(Entry $entry): void
    {
        // Индексация только если PostType имеет blueprint
        if ($entry->postType?->blueprint_id) {
            try {
                $this->indexer->index($entry);
            } catch (\Exception $e) {
                Log::error("Ошибка автоиндексации Entry {$entry->id}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Handle the Entry "deleted" event.
     *
     * Очистка индексов при удалении Entry.
     *
     * @param Entry $entry
     * @return void
     */
    public function deleted(Entry $entry): void
    {
        // Очистить индексы (CASCADE в БД, но на всякий случай)
        DocValue::where('entry_id', $entry->id)->delete();
        DocRef::where('entry_id', $entry->id)->delete();
    }

    /**
     * Обеспечить наличие валидного уникального slug для записи.
     *
     * Генерирует slug из title (если не указан) или нормализует указанный slug.
     * Проверяет глобальную уникальность slug (все записи) и зарезервированные пути.
     * Автоматически добавляет суффикс при конфликте.
     *
     * @param \App\Models\Entry $entry Запись
     * @return void
     */
    private function ensureSlug(Entry $entry): void
    {
        // Если пользователь задал кастомный slug — прогоняем через мягкий slugify
        if (!empty($entry->slug)) {
            $opts = new SlugOptions(toLower: true, asciiOnly: true);
            $entry->slug = $this->slugifier->slugify($entry->slug, $opts);
        } elseif (!empty($entry->title)) {
            // Если slug пуст — генерируем из title с явными опциями
            $opts = new SlugOptions(toLower: true, asciiOnly: true);
            $entry->slug = $this->slugifier->slugify($entry->title, $opts);
        }

        if (empty($entry->slug)) {
            return;
        }

        // Загружаем зарезервированные пути в память (кэш для производительности)
        [$prefixes, $paths] = \Illuminate\Support\Facades\Cache::remember(
            'reserved_routes_ci',
            300,
            function () {
                return [
                    ReservedRoute::where('kind', 'prefix')
                        ->pluck('path')
                        ->map(fn($p) => mb_strtolower($p, 'UTF-8'))
                        ->all(),
                    ReservedRoute::where('kind', 'path')
                        ->pluck('path')
                        ->map(fn($p) => mb_strtolower($p, 'UTF-8'))
                        ->all(),
                ];
            }
        );

        // Проверяем занятость: глобальная уникальность + зарезервированные пути
        $entry->slug = $this->uniqueSlugService->ensureUnique(
            $entry->slug,
            function (string $slug) use ($entry, $prefixes, $paths) {
                // Проверка глобальной уникальности slug (все записи, кроме текущей)
                $exists = Entry::query()
                    ->where('slug', $slug)
                    ->when($entry->exists, fn($q) => $q->where('id', '!=', $entry->id))
                    ->exists();

                // Проверка зарезервированных путей в памяти (быстрее, чем SQL)
                $slugLower = Str::lower($slug);
                $reserved = in_array($slugLower, $paths, true)
                    || in_array($slugLower, $prefixes, true)
                    || collect($prefixes)->contains(fn($prefix) => Str::startsWith($slugLower, $prefix . '/'));

                return $exists || $reserved;
            }
        );
    }

    /**
     * Санитизировать richtext поля (body_html, excerpt_html) из data_json.
     *
     * Сохраняет очищенный HTML в body_html_sanitized/excerpt_html_sanitized
     * для безопасного отображения на фронтенде.
     *
     * @param \App\Models\Entry $entry Запись
     * @return void
     */
    private function sanitizeRichTextFields(Entry $entry): void
    {
        $data = $entry->data_json ?? [];

        // Санитизируем body_html
        if (isset($data['body_html']) && is_string($data['body_html'])) {
            $data['body_html_sanitized'] = $this->sanitizer->sanitize($data['body_html']);
        }

        // Санитизируем excerpt_html
        if (isset($data['excerpt_html']) && is_string($data['excerpt_html'])) {
            $data['excerpt_html_sanitized'] = $this->sanitizer->sanitize($data['excerpt_html']);
        }

        $entry->data_json = $data;
    }
}

