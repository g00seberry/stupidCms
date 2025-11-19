<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

/**
 * Плагин метаданных, основанный на библиотеке getID3 (essence).
 *
 * Использует getID3 для извлечения метаданных видео/аудио файлов.
 * getID3 - это чистая PHP библиотека, не требующая внешних утилит.
 *
 * @package App\Domain\Media\Services
 */
class EssenceMediaMetadataPlugin implements MediaMetadataPlugin
{
    /**
     * @var \getID3 Экземпляр getID3
     */
    private \getID3 $getID3;

    /**
     * @param \getID3|null $getID3 Экземпляр getID3 (если не указан, создаётся новый)
     */
    public function __construct(?\getID3 $getID3 = null)
    {
        $this->getID3 = $getID3 ?? new \getID3();
    }

    /**
     * Проверить, поддерживает ли плагин указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если плагин может обработать файл
     */
    public function supports(string $mime): bool
    {
        return str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/');
    }

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
    public function extract(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        try {
            $fileInfo = $this->getID3->analyze($path);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($fileInfo)) {
            return [];
        }

        return $this->normalize($fileInfo);
    }

    /**
     * Нормализовать структуру getID3 в плоский массив значений.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return array{
     *     duration_ms?: int|null,
     *     bitrate_kbps?: int|null,
     *     frame_rate?: float|null,
     *     frame_count?: int|null,
     *     video_codec?: string|null,
     *     audio_codec?: string|null
     * }
     */
    private function normalize(array $data): array
    {
        $durationMs = null;
        $bitrateKbps = null;
        $frameRate = null;
        $frameCount = null;
        $videoCodec = null;
        $audioCodec = null;

        // Длительность из playtime_seconds
        if (isset($data['playtime_seconds']) && is_numeric($data['playtime_seconds'])) {
            $durationMs = (int) round(((float) $data['playtime_seconds']) * 1000);
        }

        // Битрейт из bitrate
        if (isset($data['bitrate']) && is_numeric($data['bitrate'])) {
            $bitrateKbps = (int) round(((float) $data['bitrate']) / 1000);
        }

        // Видео метаданные
        if (isset($data['video']) && is_array($data['video'])) {
            $video = $data['video'];

            // Видео кодек - пробуем разные поля
            if (isset($video['codec']) && is_string($video['codec']) && $video['codec'] !== '') {
                $videoCodec = $video['codec'];
            } elseif (isset($video['codec_fourcc']) && is_string($video['codec_fourcc']) && $video['codec_fourcc'] !== '') {
                $videoCodec = $video['codec_fourcc'];
            } elseif (isset($video['fourcc']) && is_string($video['fourcc']) && $video['fourcc'] !== '') {
                $videoCodec = $video['fourcc'];
            } elseif (isset($video['fourcc_lookup']) && is_string($video['fourcc_lookup']) && $video['fourcc_lookup'] !== '') {
                $videoCodec = $video['fourcc_lookup'];
            }

            // Частота кадров
            if (isset($video['frame_rate']) && is_numeric($video['frame_rate'])) {
                $frameRate = (float) $video['frame_rate'];
            } elseif (isset($video['frame_rate_index']) && is_numeric($video['frame_rate_index'])) {
                // Альтернативный формат frame_rate_index
                $frameRate = (float) $video['frame_rate_index'];
            }

            // Количество кадров
            if (isset($video['total_frames']) && is_numeric($video['total_frames'])) {
                $frameCount = (int) $video['total_frames'];
            }
        }

        // Для MP4 файлов кодек может быть в quicktime секции
        if ($videoCodec === null && isset($data['quicktime']['video']['codec']) && is_string($data['quicktime']['video']['codec'])) {
            $videoCodec = $data['quicktime']['video']['codec'];
        }

        // Аудио метаданные
        if (isset($data['audio']) && is_array($data['audio'])) {
            $audio = $data['audio'];

            if (isset($audio['codec']) && is_string($audio['codec'])) {
                $audioCodec = $audio['codec'];
            } elseif (isset($audio['dataformat']) && is_string($audio['dataformat'])) {
                // Альтернативный формат dataformat
                $audioCodec = $audio['dataformat'];
            }
        }

        // Если длительность не найдена в playtime_seconds, пробуем из video/audio
        if ($durationMs === null) {
            if (isset($data['video']['duration']) && is_numeric($data['video']['duration'])) {
                $durationMs = (int) round(((float) $data['video']['duration']) * 1000);
            } elseif (isset($data['audio']['duration']) && is_numeric($data['audio']['duration'])) {
                $durationMs = (int) round(((float) $data['audio']['duration']) * 1000);
            }
        }

        // Если битрейт не найден, пробуем вычислить из video/audio
        if ($bitrateKbps === null) {
            if (isset($data['video']['bitrate']) && is_numeric($data['video']['bitrate'])) {
                $bitrateKbps = (int) round(((float) $data['video']['bitrate']) / 1000);
            } elseif (isset($data['audio']['bitrate']) && is_numeric($data['audio']['bitrate'])) {
                $bitrateKbps = (int) round(((float) $data['audio']['bitrate']) / 1000);
            }
        }

        // Если frame_count не найден, вычисляем из duration и frame_rate
        if ($frameCount === null && $durationMs !== null && $frameRate !== null && $frameRate > 0) {
            $frameCount = (int) round(($durationMs / 1000.0) * $frameRate);
        }

        return [
            'duration_ms' => $durationMs,
            'bitrate_kbps' => $bitrateKbps,
            'frame_rate' => $frameRate,
            'frame_count' => $frameCount,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
        ];
    }
}

