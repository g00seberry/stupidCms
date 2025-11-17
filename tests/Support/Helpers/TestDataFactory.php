<?php

declare(strict_types=1);

namespace Tests\Support\Helpers;

use App\Models\Entry;
use App\Models\Media;

/**
 * Фабрика для создания тестовых данных.
 *
 * Предоставляет статические методы для быстрого создания тестовых сущностей
 * с предустановленными значениями по умолчанию.
 */
class TestDataFactory
{
    /**
     * Создать Media с указанными атрибутами.
     *
     * @param array<string, mixed> $attributes Атрибуты для переопределения значений по умолчанию
     * @return Media Созданный объект Media
     */
    public static function createMedia(array $attributes = []): Media
    {
        return Media::factory()->create($attributes);
    }

    /**
     * Создать Entry с указанными атрибутами.
     *
     * @param array<string, mixed> $attributes Атрибуты для переопределения значений по умолчанию
     * @return Entry Созданный объект Entry
     */
    public static function createEntry(array $attributes = []): Entry
    {
        return Entry::factory()->create($attributes);
    }
}

