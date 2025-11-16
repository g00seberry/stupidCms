<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Резолвер дисков для медиа-хранилища.
 *
 * Инкапсулирует логику выбора диска по коллекции и типу медиа (MIME/kind),
 * используя конфигурацию config/media.php:
 * - media.disks.collections
 * - media.disks.kinds
 * - media.disks.default
 */
class StorageResolver
{
    /**
     * Определить имя диска для загрузки медиа.
     *
     * Приоритет:
     * 1) media.disks.collections[collection]
     * 2) media.disks.kinds[kind], где kind выведен из MIME
     * 3) media.disks.default
     * 4) 'media' (жёсткий fallback, если конфиг не задан)
     *
     * @param string|null $collection Коллекция медиа (payload.collection)
     * @param string|null $mime MIME-тип файла
     * @return string Имя диска (ключ в config/filesystems.php)
     */
    public function resolveDiskName(?string $collection, ?string $mime = null): string
    {
        $collection = $collection !== null ? trim($collection) : null;

        /** @var array<string, mixed> $disksConfig */
        $disksConfig = config('media.disks', []);

        /** @var array<string, string> $collections */
        $collections = (array) ($disksConfig['collections'] ?? []);

        if ($collection !== null && $collection !== '' && isset($collections[$collection])) {
            return (string) $collections[$collection];
        }

        $kind = $this->detectKindFromMime($mime);

        /** @var array<string, string> $kinds */
        $kinds = (array) ($disksConfig['kinds'] ?? []);

        if ($kind !== null && isset($kinds[$kind])) {
            return (string) $kinds[$kind];
        }

        $default = $disksConfig['default'] ?? null;

        if (is_string($default) && $default !== '') {
            return $default;
        }

        return 'media';
    }

    /**
     * Получить файловую систему для загрузки медиа.
     *
     * @param string|null $collection Коллекция медиа (payload.collection)
     * @param string|null $mime MIME-тип файла
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function filesystemForUpload(?string $collection, ?string $mime = null): Filesystem
    {
        $diskName = $this->resolveDiskName($collection, $mime);

        return Storage::disk($diskName);
    }

    /**
     * Определить тип медиа (kind) по MIME-типу.
     *
     * Возвращает одно из значений: image, video, audio, document.
     *
     * @param string|null $mime MIME-тип файла
     * @return string|null Тип медиа или null, если не удалось определить
     */
    private function detectKindFromMime(?string $mime): ?string
    {
        if (! is_string($mime) || $mime === '') {
            return null;
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }
}


