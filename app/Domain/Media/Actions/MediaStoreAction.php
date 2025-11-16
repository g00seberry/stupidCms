<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Действие для сохранения медиа-файла.
 *
 * Обрабатывает загрузку файла: сохранение на диск, извлечение метаданных,
 * создание записи Media в БД.
 *
 * @package App\Domain\Media\Actions
 */
class MediaStoreAction
{
    /**
     * @param \App\Domain\Media\Services\MediaMetadataExtractor $metadataExtractor Извлекатель метаданных
     */
    public function __construct(
        private readonly MediaMetadataExtractor $metadataExtractor
    ) {
    }

    /**
     * Выполнить сохранение медиа-файла.
     *
     * Сохраняет файл на диск, извлекает метаданные (размеры, EXIF и т.д.),
     * вычисляет checksum и создаёт запись Media в БД.
     * Если файл с таким же checksum уже существует, возвращает существующую запись
     * без сохранения дубликата на диск (дедупликация).
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param array<string, mixed> $payload Дополнительные данные (title, alt, collection)
     * @return \App\Models\Media Созданная или существующая запись Media
     * @throws \RuntimeException Если не удалось сохранить файл на диск
     */
    public function execute(UploadedFile $file, array $payload = []): Media
    {
        $diskName = config('media.disk', 'media');
        $disk = Storage::disk($diskName);

        $mime = $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';
        $sizeBytes = (int) ($file->getSize() ?? 0);
        $originalName = $file->getClientOriginalName() ?: $file->getFilename();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: $file->extension() ?: 'bin');
        $checksum = $this->checksum($file);

        // Дедупликация: проверка существующего файла по checksum
        if ($checksum !== null) {
            $existing = Media::where('checksum_sha256', $checksum)->first();
            if ($existing !== null) {
                // Обновить метаданные, если они переданы в payload
                $shouldUpdate = false;
                $updates = [];

                if (isset($payload['title']) && $existing->title !== ($payload['title'] ?? null)) {
                    $updates['title'] = $payload['title'] ?? null;
                    $shouldUpdate = true;
                }

                if (isset($payload['alt']) && $existing->alt !== ($payload['alt'] ?? null)) {
                    $updates['alt'] = $payload['alt'] ?? null;
                    $shouldUpdate = true;
                }

                if (isset($payload['collection']) && $existing->collection !== ($payload['collection'] ?? null)) {
                    $updates['collection'] = $payload['collection'] ?? null;
                    $shouldUpdate = true;
                }

                if ($shouldUpdate) {
                    $existing->update($updates);
                }

                return $existing;
            }
        }

        $path = $this->storeFile($disk, $file, $extension, $checksum);

        $metadata = $this->metadataExtractor->extract($file, $mime);

        return Media::create([
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $originalName,
            'ext' => $extension,
            'mime' => $mime,
            'size_bytes' => $sizeBytes > 0 ? $sizeBytes : $disk->size($path),
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'duration_ms' => $metadata['duration_ms'],
            'checksum_sha256' => $checksum,
            'exif_json' => $metadata['exif'],
            'title' => $payload['title'] ?? null,
            'alt' => $payload['alt'] ?? null,
            'collection' => $payload['collection'] ?? null,
        ]);
    }

    /**
     * Сохранить файл на диск.
     *
     * Использует стратегию организации путей (by-date или hash-shard).
     * Генерирует уникальное имя файла на основе ULID.
     *
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk Диск для сохранения
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $extension Расширение файла
     * @param string|null $checksum SHA256 checksum файла (для hash-shard стратегии)
     * @return string Путь к сохранённому файлу
     * @throws \RuntimeException Если не удалось сохранить файл
     */
    private function storeFile(Filesystem $disk, UploadedFile $file, string $extension, ?string $checksum): string
    {
        $strategy = config('media.path_strategy', 'by-date');
        $baseName = strtolower((string) Str::ulid());
        $directory = match ($strategy) {
            'hash-shard' => $this->hashShardDirectory($checksum),
            default => now('UTC')->format('Y/m/d'),
        };

        $filename = $extension !== '' ? "{$baseName}.{$extension}" : $baseName;
        $path = trim($directory, '/');
        $fullPath = $path === '' ? $filename : "{$path}/{$filename}";

        $targetDirectory = $path === '' ? '' : $path;

        $storedPath = $disk->putFileAs($targetDirectory, $file, $filename);

        if (! $storedPath) {
            throw new RuntimeException('Failed to store uploaded media file.');
        }

        return str_replace('\\', '/', ltrim($storedPath, '/'));
    }

    /**
     * Вычислить SHA256 checksum файла.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @return string|null Checksum или null, если не удалось вычислить
     */
    private function checksum(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (! $realPath || ! is_file($realPath)) {
            return null;
        }

        return hash_file('sha256', $realPath);
    }

    /**
     * Сформировать директорию на основе hash-shard стратегии.
     *
     * Использует первые 4 символа checksum для создания структуры директорий (XX/YY).
     * Если checksum недоступен, использует дату.
     *
     * @param string|null $checksum SHA256 checksum
     * @return string Путь директории (например, 'a1/b2')
     */
    private function hashShardDirectory(?string $checksum): string
    {
        if ($checksum === null || strlen($checksum) < 4) {
            return now('UTC')->format('Y/m/d');
        }

        return substr($checksum, 0, 2).'/'.substr($checksum, 2, 2);
    }
}


