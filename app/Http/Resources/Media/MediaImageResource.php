<?php

declare(strict_types=1);

namespace App\Http\Resources\Media;

use App\Domain\Media\MediaKind;
use App\Models\Media;
use Illuminate\Http\Request;

/**
 * API Resource для изображений (Media).
 *
 * Возвращает специфичные поля для изображений:
 * width, height, preview_urls (обязательные).
 *
 * Требует загруженную связь `image` для получения размеров.
 *
 * @package App\Http\Resources\Media
 */
class MediaImageResource extends BaseMediaResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Включает базовые поля и специфичные для изображений:
     * width, height, preview_urls.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями изображения
     */
    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        // Загружаем связь, если не загружена
        if (! $media->relationLoaded('image')) {
            $media->load('image');
        }

        $image = $media->image;

        $base = parent::toArray($request);

        // Если связь отсутствует, возвращаем null для размеров
        // Это может произойти, если изображение было создано без метаданных
        return array_merge($base, [
            'width' => $image?->width ? (int) $image->width : null,
            'height' => $image?->height ? (int) $image->height : null,
            'preview_urls' => $this->previewUrls(),
        ]);
    }

    /**
     * Сформировать preview URLs для вариантов изображения.
     *
     * Возвращает массив URL для всех настроенных вариантов изображения
     * (thumbnail, medium, large и т.д.).
     *
     * @return array<string, string> Массив [variant => URL]
     */
    private function previewUrls(): array
    {
        /** @var Media $media */
        $media = $this->resource;

        $urls = [];

        foreach (array_keys(config('media.variants', [])) as $variant) {
            $urls[$variant] = route('api.v1.media.show', [
                'id' => $media->id,
                'variant' => $variant,
            ]);
        }

        return $urls;
    }
}

