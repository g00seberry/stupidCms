<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Трейт для настройки фейкового хранилища в тестах.
 *
 * Предоставляет метод для быстрой настройки Storage::fake().
 */
trait UsesFakeStorage
{
    /**
     * Настроить фейковое хранилище для указанного диска.
     *
     * @param string $disk Имя диска для настройки (по умолчанию 'media')
     */
    protected function setUpFakeStorage(string $disk = 'media'): void
    {
        Storage::fake($disk);
    }
}

