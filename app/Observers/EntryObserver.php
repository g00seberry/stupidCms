<?php

namespace App\Observers;

use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Support\EntrySlug\EntrySlugService;
use App\Support\Slug\Slugifier;
use App\Support\Slug\SlugOptions;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EntryObserver
{
    /**
     * Временное хранилище для старых slug'ов (по ID записи)
     * Используется для передачи старого slug из updating() в updated()
     */
    private static array $oldSlugs = [];

    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService,
        private EntrySlugService $entrySlugService,
    ) {}

    public function creating(Entry $entry): void
    {
        $this->ensureSlug($entry);
    }

    public function updating(Entry $entry): void
    {
        // Если изменился title или slug, пересчитываем
        if ($entry->isDirty(['title', 'slug'])) {
            // Сохраняем оригинальный slug ДО изменения (для истории)
            // Важно: читаем getOriginal() до вызова ensureSlug(), так как ensureSlug может изменить slug
            $oldSlug = $entry->getOriginal('slug');
            $this->ensureSlug($entry);
            // Сохраняем старый slug во временном хранилище для использования в updated()
            if ($entry->exists) {
                self::$oldSlugs[$entry->id] = $oldSlug;
            }
        }
    }

    public function created(Entry $entry): void
    {
        $this->entrySlugService->onCreated($entry);
    }

    public function updated(Entry $entry): void
    {
        if ($entry->wasChanged('slug')) {
            // Используем сохраненный оригинальный slug из временного хранилища
            $oldSlug = self::$oldSlugs[$entry->id] ?? $entry->getOriginal('slug');
            $this->entrySlugService->onUpdated($entry, $oldSlug);
            // Очищаем временное хранилище
            unset(self::$oldSlugs[$entry->id]);
        }
    }

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

        // Получаем post_type_id для скоупа
        $postTypeId = $entry->post_type_id ?? $entry->postType?->id;

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

        // Проверяем занятость: в скоупе типа записи + зарезервированные пути
        $entry->slug = $this->uniqueSlugService->ensureUnique(
            $entry->slug,
            function (string $slug) use ($entry, $postTypeId, $prefixes, $paths) {
                // Проверка уникальности в скоупе post_type_id
                $exists = Entry::query()
                    ->where('slug', $slug)
                    ->where('post_type_id', $postTypeId)
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
}

