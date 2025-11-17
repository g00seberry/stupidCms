<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

/**
 * Трейт для настройки конфигурации Media в тестах.
 *
 * Предоставляет метод для установки стандартных значений конфигурации Media.
 */
trait ConfiguresMedia
{
    /**
     * Настроить стандартные значения конфигурации Media.
     *
     * Устанавливает базовые значения для:
     * - media.disks (default, collections, kinds)
     * - media.allowed_mimes (список разрешенных MIME-типов)
     */
    protected function configureMediaDefaults(): void
    {
        config()->set('media.disks', [
            'default' => 'media',
            'collections' => [],
            'kinds' => [],
        ]);

        config()->set('media.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/mp4',
            'audio/mpeg',
            'application/pdf',
        ]);
    }
}

