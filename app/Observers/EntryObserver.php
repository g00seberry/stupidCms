<?php

namespace App\Observers;

use App\Models\Entry;
use App\Models\ReservedRoute;
use App\Support\Slug\Slugifier;
use App\Support\Slug\SlugOptions;
use App\Support\Slug\UniqueSlugService;

class EntryObserver
{
    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService,
    ) {}

    public function creating(Entry $entry): void
    {
        $this->ensureSlug($entry);
    }

    public function updating(Entry $entry): void
    {
        // Если изменился title или slug, пересчитываем
        if ($entry->isDirty(['title', 'slug'])) {
            $this->ensureSlug($entry);
        }
    }

    private function ensureSlug(Entry $entry): void
    {
        // Если пользователь задал кастомный slug — прогоняем через мягкий slugify
        if (!empty($entry->slug)) {
            $opts = new SlugOptions(toLower: true, asciiOnly: true);
            $entry->slug = $this->slugifier->slugify($entry->slug, $opts);
        } elseif (!empty($entry->title)) {
            // Если slug пуст — генерируем из title
            $entry->slug = $this->slugifier->slugify($entry->title);
        }

        if (empty($entry->slug)) {
            return;
        }

        // Получаем post_type для скоупа
        $postType = $entry->postType ?? $entry->postType()->first();
        $postTypeSlug = $postType?->slug ?? 'page';

        // Проверяем занятость: в скоупе типа записи + зарезервированные пути
        $entry->slug = $this->uniqueSlugService->ensureUnique(
            $entry->slug,
            function (string $slug) use ($entry, $postTypeSlug) {
                // Проверка уникальности в скоупе post_type
                $exists = Entry::query()
                    ->where('slug', $slug)
                    ->whereHas('postType', fn($q) => $q->where('slug', $postTypeSlug))
                    ->when($entry->exists, fn($q) => $q->where('id', '!=', $entry->id))
                    ->exists();

                // Проверка зарезервированных путей
                $reserved = ReservedRoute::query()
                    ->where('path', $slug)
                    ->orWhere(function ($q) use ($slug) {
                        $q->where('kind', 'prefix')
                            ->where('path', 'like', $slug . '/%');
                    })
                    ->exists();

                return $exists || $reserved;
            }
        );
    }
}

