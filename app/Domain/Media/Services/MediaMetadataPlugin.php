<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

/**
 * Плагин для извлечения метаданных медиа-файлов (главным образом видео/аудио).
 *
 * Реализации могут использовать внешние утилиты (ffprobe/mediainfo и т.п.)
 * и должны возвращать нормализованный набор полей.
 *
 * @package App\Domain\Media\Services
 */
interface MediaMetadataPlugin
{
    /**
     * Проверить, поддерживает ли плагин указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если плагин может обработать файл
     */
    public function supports(string $mime): bool;

    /**
     * Извлечь нормализованные метаданные из файла.
     *
     * @param string $path Абсолютный путь к файлу
     * @return array{
     *     duration_ms?: int|null,
     *     bitrate_kbps?: int|null,
     *     frame_rate?: float|null,
     *     frame_count?: int|null,
     *     video_codec?: string|null,
     *     audio_codec?: string|null
     * }
     */
    public function extract(string $path): array;
}


