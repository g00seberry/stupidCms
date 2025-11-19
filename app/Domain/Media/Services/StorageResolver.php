<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\MediaKind;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Резолвер дисков для медиа-хранилища.
 *
 * Инкапсулирует логику выбора диска по типу медиа (MIME/kind),
 * используя конфигурацию config/media.php:
 * - media.disks.kinds
 * - media.disks.default
 */
class StorageResolver
{
    /**
     * Определить имя диска для загрузки медиа.
     *
     * Приоритет:
     * 1) media.disks.kinds[kind], где kind выведен из MIME
     * 2) media.disks.default
     * 3) 'media' (жёсткий fallback, если конфиг не задан)
     *
     * @param string|null $mime MIME-тип файла
     * @return string Имя диска (ключ в config/filesystems.php)
     */
    public function resolveDiskName(?string $mime = null): string
    {
        $kind = $this->detectKindFromMime($mime);

        /** @var array<string, mixed> $disksConfig */
        $disksConfig = config('media.disks', []);

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
     * @param string|null $mime MIME-тип файла
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function filesystemForUpload(?string $mime = null): Filesystem
    {
        $diskName = $this->resolveDiskName($mime);

        return Storage::disk($diskName);
    }

    /**
     * Определить тип медиа (kind) по MIME-типу.
     *
     * Возвращает строковое значение MediaKind для использования в конфигурации.
     *
     * @param string|null $mime MIME-тип файла
     * @return string|null Тип медиа (image, video, audio, document) или null, если не удалось определить
     */
    private function detectKindFromMime(?string $mime): ?string
    {
        if (! is_string($mime) || $mime === '') {
            return null;
        }

        return MediaKind::fromMime($mime)->value;
    }
}


