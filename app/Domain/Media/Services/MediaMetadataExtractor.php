<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\DTO\MediaMetadataDTO;
use App\Domain\Media\Images\ImageProcessor;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\UploadedFile;

/**
 * Сервис для извлечения метаданных из медиа-файлов.
 *
 * Извлекает размеры изображений, EXIF данные и другую информацию
 * из загруженных файлов. Использует плагины (getID3, ffprobe/mediainfo/exiftool)
 * с graceful fallback и кэшированием результатов.
 *
 * @package App\Domain\Media\Services
 */
class MediaMetadataExtractor
{
    /**
     * @param \App\Domain\Media\Images\ImageProcessor $images Процессор изображений
     * @param iterable<\App\Domain\Media\Services\MediaMetadataPlugin> $plugins Плагины для извлечения медиаметаданных
     * @param \Illuminate\Contracts\Cache\Repository|null $cache Кэш для метаданных
     * @param int $cacheTtl TTL кэша в секундах (по умолчанию 3600)
     */
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly iterable $plugins = [],
        private readonly ?CacheRepository $cache = null,
        private readonly int $cacheTtl = 3600
    ) {
    }

    /**
     * Извлечь метаданные из медиа-файла.
     *
     * Для изображений извлекает размеры (width, height) и EXIF данные (если доступны).
 * Для видео/аудио использует плагины (getID3, ffprobe/mediainfo/exiftool) для извлечения
 * длительности и дополнительных нормализованных полей (bitrate, frameRate, codecs)
 * с graceful fallback.
     *
     * Извлечённые данные возвращаются в виде MediaMetadataDTO, который затем используется
     * для создания записей в специализированных таблицах:
     * - MediaImage (width, height, exif_json) для изображений
     * - MediaAvMetadata (duration_ms, bitrate_kbps, frame_rate, codecs) для видео/аудио
     *
     * Результаты кэшируются для оптимизации производительности.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string|null $mime MIME-тип файла (если не указан, определяется автоматически)
     * @return \App\Domain\Media\DTO\MediaMetadataDTO Метаданные файла
     */
    public function extract(UploadedFile $file, ?string $mime = null): MediaMetadataDTO
    {
        $mime ??= $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';

        // Пытаемся получить из кэша
        $cacheKey = $this->getCacheKey($file, $mime);
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof MediaMetadataDTO) {
                return $cached;
            }
        }

        $dto = $this->extractMetadata($file, $mime);

        // Сохраняем в кэш
        if ($this->cache !== null) {
            $this->cache->put($cacheKey, $dto, $this->cacheTtl);
        }

        return $dto;
    }

    /**
     * Извлечь метаданные без кэширования.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return \App\Domain\Media\DTO\MediaMetadataDTO
     */
    private function extractMetadata(UploadedFile $file, string $mime): MediaMetadataDTO
    {
        $width = null;
        $height = null;
        $duration = null;
        $exif = null;
        $bitrateKbps = null;
        $frameRate = null;
        $frameCount = null;
        $videoCodec = null;
        $audioCodec = null;

        if (str_starts_with($mime, 'image/')) {
            // Пытаемся через универсальный процессор (даже если GD не поддерживает формат)
            $bytes = @file_get_contents($file->getRealPath() ?: $file->getPathname() ?: '');
            if (is_string($bytes) && $bytes !== '') {
                try {
                    $img = $this->images->open($bytes);
                    $width = $this->images->width($img);
                    $height = $this->images->height($img);
                    $this->images->destroy($img);
                } catch (\Throwable) {
                    // Fallback на getimagesize, если драйвер не смог открыть
                    $imageInfo = @getimagesize($file->getRealPath() ?: $file->getPathname() ?: '');

                    if (is_array($imageInfo)) {
                        $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : null;
                        $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : null;
                    }
                }
            }

            if ($this->canReadExif($mime)) {
                $exif = $this->readExif($file);
            }
        } elseif (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/')) {
            $path = $file->getRealPath() ?: $file->getPathname() ?: null;

            if (is_string($path) && $path !== '') {
                // Пробуем плагины по порядку с graceful fallback
                $pluginData = null;
                foreach ($this->plugins as $plugin) {
                    if (! $plugin instanceof MediaMetadataPlugin) {
                        continue;
                    }

                    if (! $plugin->supports($mime)) {
                        continue;
                    }

                    try {
                        $pluginData = $plugin->extract($path);
                        // Если плагин вернул данные, используем их
                        if (! empty($pluginData)) {
                            break;
                        }
                    } catch (\Throwable) {
                        // Продолжаем со следующим плагином
                        continue;
                    }
                }

                if ($pluginData !== null) {
                    $duration = $pluginData['duration_ms'] ?? null;
                    $bitrateKbps = $pluginData['bitrate_kbps'] ?? null;
                    $frameRate = $pluginData['frame_rate'] ?? null;
                    $frameCount = $pluginData['frame_count'] ?? null;
                    $videoCodec = $pluginData['video_codec'] ?? null;
                    $audioCodec = $pluginData['audio_codec'] ?? null;
                }
            }
        }

        return new MediaMetadataDTO(
            width: $width,
            height: $height,
            durationMs: $duration,
            exif: $exif,
            bitrateKbps: $bitrateKbps,
            frameRate: $frameRate,
            frameCount: $frameCount,
            videoCodec: $videoCodec,
            audioCodec: $audioCodec
        );
    }

    /**
     * Получить ключ кэша для файла.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return string
     */
    private function getCacheKey(UploadedFile $file, string $mime): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $size = (int) ($file->getSize() ?? 0);
        $mtime = is_file($path) ? filemtime($path) : 0;

        return sprintf('media:metadata:%s:%s:%d:%d', md5($path), $mime, $size, $mtime);
    }

    /**
     * Проверить, можно ли читать EXIF данные для данного MIME-типа.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если EXIF доступен для этого типа
     */
    private function canReadExif(string $mime): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        return in_array($mime, ['image/jpeg', 'image/tiff'], true);
    }

    /**
     * Прочитать EXIF данные из файла.
     *
     * Нормализует EXIF данные, оставляя только скалярные значения.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @return array<string, array<string, mixed>>|null EXIF данные или null, если не удалось прочитать
     */
    private function readExif(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data)) {
            return null;
        }

        $normalized = [];
        foreach ($data as $section => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (! is_string($key) || ! is_scalar($value)) {
                    continue;
                }

                // EXIF может содержать бинарные/не-UTF8 значения (например MakerNote/PrintIM),
                // которые ломают json_encode() и database cache (MySQL "Incorrect string value").
                if (is_string($value) && ! preg_match('//u', $value)) {
                    continue;
                }

                $normalized[$section][$key] = $value;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}


