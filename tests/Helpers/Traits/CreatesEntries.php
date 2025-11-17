<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\Models\Entry;
use App\Models\PostType;

/**
 * Трейт для упрощения создания записей контента в тестах.
 */
trait CreatesEntries
{
    /**
     * Создать запись контента.
     *
     * @param array<string, mixed> $attributes Атрибуты для фабрики
     * @return Entry
     */
    protected function createEntry(array $attributes = []): Entry
    {
        return Entry::factory()->create($attributes);
    }

    /**
     * Создать опубликованную запись.
     *
     * @param array<string, mixed> $attributes Атрибуты для фабрики
     * @return Entry
     */
    protected function createPublishedEntry(array $attributes = []): Entry
    {
        return Entry::factory()->published()->create($attributes);
    }

    /**
     * Создать черновик записи.
     *
     * @param array<string, mixed> $attributes Атрибуты для фабрики
     * @return Entry
     */
    protected function createDraftEntry(array $attributes = []): Entry
    {
        return Entry::factory()->draft()->create($attributes);
    }

    /**
     * Создать тип записи.
     *
     * @param array<string, mixed> $attributes Атрибуты для фабрики
     * @return PostType
     */
    protected function createPostType(array $attributes = []): PostType
    {
        return PostType::factory()->create($attributes);
    }
}

