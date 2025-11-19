<?php

declare(strict_types=1);

namespace App\Domain\Media\DTO;

/**
 * DTO для нормализованных метаданных медиа-файла.
 *
 * Представляет унифицированную структуру метаданных, извлечённых
 * из различных источников (ImageProcessor, ffprobe, mediainfo, exiftool).
 *
 * Данные из DTO используются для создания записей в специализированных таблицах:
 * - width, height, exif → MediaImage (для изображений)
 * - durationMs, bitrateKbps, frameRate, frameCount, videoCodec, audioCodec → MediaAvMetadata (для видео/аудио)
 *
 * @package App\Domain\Media\DTO
 */
readonly class MediaMetadataDTO
{
    /**
     * @param int|null $width Ширина изображения в пикселях
     * @param int|null $height Высота изображения в пикселях
     * @param int|null $durationMs Длительность медиа в миллисекундах
     * @param array<string, array<string, mixed>>|null $exif EXIF метаданные (нормализованные)
     * @param int|null $bitrateKbps Битрейт в килобитах в секунду
     * @param float|null $frameRate Частота кадров в секунду
     * @param int|null $frameCount Количество кадров
     * @param string|null $videoCodec Кодек видео
     * @param string|null $audioCodec Кодек аудио
     */
    public function __construct(
        public ?int $width = null,
        public ?int $height = null,
        public ?int $durationMs = null,
        public ?array $exif = null,
        public ?int $bitrateKbps = null,
        public ?float $frameRate = null,
        public ?int $frameCount = null,
        public ?string $videoCodec = null,
        public ?string $audioCodec = null
    ) {
    }

    /**
     * Преобразовать DTO в массив для сохранения в БД.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'duration_ms' => $this->durationMs,
            'exif' => $this->exif,
            'bitrate_kbps' => $this->bitrateKbps,
            'frame_rate' => $this->frameRate,
            'frame_count' => $this->frameCount,
            'video_codec' => $this->videoCodec,
            'audio_codec' => $this->audioCodec,
        ];
    }

    /**
     * Создать DTO из массива.
     *
     * @param array<string, mixed> $data Данные метаданных
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            width: isset($data['width']) && is_int($data['width']) ? $data['width'] : null,
            height: isset($data['height']) && is_int($data['height']) ? $data['height'] : null,
            durationMs: isset($data['duration_ms']) && is_int($data['duration_ms']) ? $data['duration_ms'] : null,
            exif: isset($data['exif']) && is_array($data['exif']) ? $data['exif'] : null,
            bitrateKbps: isset($data['bitrate_kbps']) && is_int($data['bitrate_kbps']) ? $data['bitrate_kbps'] : null,
            frameRate: isset($data['frame_rate']) && is_float($data['frame_rate']) ? $data['frame_rate'] : null,
            frameCount: isset($data['frame_count']) && is_int($data['frame_count']) ? $data['frame_count'] : null,
            videoCodec: isset($data['video_codec']) && is_string($data['video_codec']) ? $data['video_codec'] : null,
            audioCodec: isset($data['audio_codec']) && is_string($data['audio_codec']) ? $data['audio_codec'] : null
        );
    }
}

