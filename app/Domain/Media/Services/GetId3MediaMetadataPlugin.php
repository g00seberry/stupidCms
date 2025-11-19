<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

/**
 * Плагин метаданных, основанный на библиотеке getID3.
 *
 * Использует getID3 для извлечения метаданных видео/аудио файлов.
 * getID3 - это чистая PHP библиотека, не требующая внешних утилит.
 *
 * @package App\Domain\Media\Services
 */
class GetId3MediaMetadataPlugin implements MediaMetadataPlugin
{
    /**
     * Приоритетные поля для извлечения видео-кодека.
     *
     * @var array<int, string>
     */
    private const VIDEO_CODEC_FIELDS = ['codec', 'codec_fourcc', 'fourcc', 'fourcc_lookup'];

    /**
     * Приоритетные поля для извлечения аудио-кодека.
     *
     * @var array<int, string>
     */
    private const AUDIO_CODEC_FIELDS = ['codec', 'dataformat'];

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
        $durationMs = $this->extractDuration($data);
        $bitrateKbps = $this->extractBitrate($data);
        $frameRate = $this->extractFrameRate($data);
        $videoCodec = $this->extractVideoCodec($data);
        $audioCodec = $this->extractAudioCodec($data);
        $frameCount = $this->extractFrameCount($data, $durationMs, $frameRate);

        return [
            'duration_ms' => $durationMs,
            'bitrate_kbps' => $bitrateKbps,
            'frame_rate' => $frameRate,
            'frame_count' => $frameCount,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
        ];
    }

    /**
     * Извлечь длительность в миллисекундах.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return int|null Длительность в миллисекундах или null
     */
    private function extractDuration(array $data): ?int
    {
        // Основной источник - playtime_seconds
        $seconds = $this->extractNumeric($data, 'playtime_seconds');
        if ($seconds !== null && $seconds > 0) {
            return (int) round($seconds * 1000);
        }

        // Fallback: video duration
        $videoDuration = $this->extractNumeric($data, 'video', 'duration');
        if ($videoDuration !== null && $videoDuration > 0) {
            return (int) round($videoDuration * 1000);
        }

        // Fallback: audio duration
        $audioDuration = $this->extractNumeric($data, 'audio', 'duration');
        if ($audioDuration !== null && $audioDuration > 0) {
            return (int) round($audioDuration * 1000);
        }

        return null;
    }

    /**
     * Извлечь битрейт в килобитах в секунду.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return int|null Битрейт в кбит/с или null
     */
    private function extractBitrate(array $data): ?int
    {
        // Основной источник - общий bitrate
        $bitrate = $this->extractNumeric($data, 'bitrate');
        if ($bitrate !== null && $bitrate > 0) {
            return (int) round($bitrate / 1000);
        }

        // Fallback: video bitrate
        $videoBitrate = $this->extractNumeric($data, 'video', 'bitrate');
        if ($videoBitrate !== null && $videoBitrate > 0) {
            return (int) round($videoBitrate / 1000);
        }

        // Fallback: audio bitrate
        $audioBitrate = $this->extractNumeric($data, 'audio', 'bitrate');
        if ($audioBitrate !== null && $audioBitrate > 0) {
            return (int) round($audioBitrate / 1000);
        }

        return null;
    }

    /**
     * Извлечь частоту кадров.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return float|null Частота кадров или null
     */
    private function extractFrameRate(array $data): ?float
    {
        $frameRate = $this->extractNumeric($data, 'video', 'frame_rate');
        if ($frameRate !== null && $frameRate > 0) {
            return (float) $frameRate;
        }

        // Альтернативный формат frame_rate_index
        $frameRateIndex = $this->extractNumeric($data, 'video', 'frame_rate_index');
        if ($frameRateIndex !== null && $frameRateIndex > 0) {
            return (float) $frameRateIndex;
        }

        return null;
    }

    /**
     * Извлечь видео-кодек.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return string|null Название видео-кодека или null
     */
    private function extractVideoCodec(array $data): ?string
    {
        $video = $this->extractArray($data, 'video');
        if ($video === null) {
            return null;
        }

        // Пробуем приоритетные поля
        $codec = $this->extractStringFromFields($video, self::VIDEO_CODEC_FIELDS);
        if ($codec !== null) {
            return $codec;
        }

        // Для MP4 файлов кодек может быть в quicktime секции
        $quicktimeVideo = $this->extractArray($data, 'quicktime', 'video');
        if ($quicktimeVideo !== null) {
            $quicktimeCodec = $this->extractString($quicktimeVideo, 'codec');
            if ($quicktimeCodec !== null) {
                return $quicktimeCodec;
            }
        }

        return null;
    }

    /**
     * Извлечь аудио-кодек.
     *
     * @param array<string, mixed> $data Данные getID3
     * @return string|null Название аудио-кодека или null
     */
    private function extractAudioCodec(array $data): ?string
    {
        $audio = $this->extractArray($data, 'audio');
        if ($audio === null) {
            return null;
        }

        return $this->extractStringFromFields($audio, self::AUDIO_CODEC_FIELDS);
    }

    /**
     * Извлечь количество кадров.
     *
     * Если не найдено напрямую, вычисляется из длительности и частоты кадров.
     *
     * @param array<string, mixed> $data Данные getID3
     * @param int|null $durationMs Длительность в миллисекундах
     * @param float|null $frameRate Частота кадров
     * @return int|null Количество кадров или null
     */
    private function extractFrameCount(array $data, ?int $durationMs, ?float $frameRate): ?int
    {
        // Пробуем извлечь напрямую
        $totalFrames = $this->extractInt($data, 'video', 'total_frames');
        if ($totalFrames !== null && $totalFrames > 0) {
            return $totalFrames;
        }

        // Вычисляем из duration и frame_rate
        if ($durationMs !== null && $frameRate !== null && $frameRate > 0) {
            return (int) round(($durationMs / 1000.0) * $frameRate);
        }

        return null;
    }

    /**
     * Извлечь строковое значение из массива по приоритетным полям.
     *
     * @param array<string, mixed> $data Массив данных
     * @param array<int, string> $fields Приоритетные поля для проверки
     * @return string|null Первое найденное непустое строковое значение или null
     */
    private function extractStringFromFields(array $data, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $this->extractString($data, $field);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Извлечь строковое значение из вложенного массива по пути ключей.
     *
     * @param array<string, mixed> $data Массив данных
     * @param string ...$keys Путь к значению (ключи через точку)
     * @return string|null Строковое значение или null
     */
    private function extractString(array $data, string ...$keys): ?string
    {
        $value = $this->extractValue($data, ...$keys);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Извлечь числовое значение из вложенного массива по пути ключей.
     *
     * @param array<string, mixed> $data Массив данных
     * @param string ...$keys Путь к значению (ключи через точку)
     * @return float|null Числовое значение или null
     */
    private function extractNumeric(array $data, string ...$keys): ?float
    {
        $value = $this->extractValue($data, ...$keys);
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Извлечь целочисленное значение из вложенного массива по пути ключей.
     *
     * @param array<string, mixed> $data Массив данных
     * @param string ...$keys Путь к значению (ключи через точку)
     * @return int|null Целочисленное значение или null
     */
    private function extractInt(array $data, string ...$keys): ?int
    {
        $value = $this->extractNumeric($data, ...$keys);
        if ($value !== null) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Извлечь массив из вложенной структуры по пути ключей.
     *
     * @param array<string, mixed> $data Массив данных
     * @param string ...$keys Путь к массиву (ключи через точку)
     * @return array<string, mixed>|null Массив или null
     */
    private function extractArray(array $data, string ...$keys): ?array
    {
        $value = $this->extractValue($data, ...$keys);
        if (is_array($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Извлечь значение из вложенной структуры по пути ключей.
     *
     * @param array<string, mixed> $data Массив данных
     * @param string ...$keys Путь к значению (ключи через точку)
     * @return mixed Значение или null
     */
    private function extractValue(array $data, string ...$keys): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (! is_array($current) || ! isset($current[$key])) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
