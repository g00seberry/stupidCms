<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Media\MediaKind;
use App\Http\Resources\Media\BaseMediaResource;
use App\Http\Resources\Media\MediaAudioResource;
use App\Http\Resources\Media\MediaDocumentResource;
use App\Http\Resources\Media\MediaImageResource;
use App\Http\Resources\Media\MediaVideoResource;
use App\Models\Media;

/**
 * Фабрика для создания специализированных ресурсов медиа.
 *
 * Автоматически выбирает нужный ресурс в зависимости от типа медиа:
 * - MediaImageResource для изображений
 * - MediaVideoResource для видео
 * - MediaAudioResource для аудио
 * - MediaDocumentResource для документов
 *
 * Использование:
 * ```php
 * $resource = MediaResource::make($media);
 * return $resource;
 * ```
 *
 * @package App\Http\Resources
 */
class MediaResource
{
    /**
     * Создать специализированный ресурс для медиа-файла.
     *
     * Выбирает нужный ресурс на основе типа медиа (kind).
     *
     * @param \App\Models\Media $media Медиа-файл
     * @return \App\Http\Resources\Media\BaseMediaResource Специализированный ресурс
     */
    public static function make(Media $media): BaseMediaResource
    {
        return match ($media->kind()) {
            MediaKind::Image => new MediaImageResource($media),
            MediaKind::Video => new MediaVideoResource($media),
            MediaKind::Audio => new MediaAudioResource($media),
            MediaKind::Document => new MediaDocumentResource($media),
        };
    }
}


