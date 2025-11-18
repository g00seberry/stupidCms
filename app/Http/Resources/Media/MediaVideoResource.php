<?php

declare(strict_types=1);

namespace App\Http\Resources\Media;

use App\Models\Media;
use Illuminate\Http\Request;

/**
 * API Resource для видео (Media).
 *
 * Возвращает специфичные поля для видео:
 * duration_ms, bitrate_kbps, frame_rate, frame_count, video_codec, audio_codec.
 *
 * Требует загруженную связь `avMetadata` для получения AV-метаданных.
 * Все AV-поля опциональны (могут быть null).
 *
 * @package App\Http\Resources\Media
 */
class MediaVideoResource extends BaseMediaResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Включает базовые поля и специфичные для видео:
     * duration_ms, bitrate_kbps, frame_rate, frame_count, video_codec, audio_codec.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями видео
     */
    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        // Загружаем связь, если не загружена
        if (! $media->relationLoaded('avMetadata')) {
            $media->load('avMetadata');
        }

        $avMetadata = $media->avMetadata;

        $base = parent::toArray($request);

        return array_merge($base, [
            'duration_ms' => $avMetadata?->duration_ms ? (int) $avMetadata->duration_ms : null,
            'bitrate_kbps' => $avMetadata?->bitrate_kbps ? (int) $avMetadata->bitrate_kbps : null,
            'frame_rate' => $avMetadata?->frame_rate ? (float) $avMetadata->frame_rate : null,
            'frame_count' => $avMetadata?->frame_count ? (int) $avMetadata->frame_count : null,
            'video_codec' => $avMetadata?->video_codec,
            'audio_codec' => $avMetadata?->audio_codec,
        ]);
    }
}

