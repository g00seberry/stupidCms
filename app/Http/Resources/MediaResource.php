<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Media\MediaKind;
use App\Http\Resources\Admin\AdminJsonResource;
use App\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для Media в админ-панели.
 *
 * Форматирует медиа-файл для ответа API, включая preview URLs
 * для вариантов изображений и download URL.
 *
 * @package App\Http\Resources
 */
class MediaResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Включает метаданные файла, preview URLs для вариантов изображений
     * и download URL.
     *
     * Получает width, height из связанной таблицы MediaImage,
     * duration_ms из связанной таблицы MediaAvMetadata.
     * Для корректной работы необходимо загружать связи через eager loading.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями медиа-файла
     */
    public function toArray($request): array
    {
        $previewUrls = $this->previewUrls();

        // Прямой доступ к связанным таблицам
        $image = $this->image;
        $avMetadata = $this->avMetadata;

        return [
            'id' => $this->id,
            'kind' => $this->resource->kind()->value,
            'name' => $this->original_name,
            'ext' => $this->ext,
            'mime' => $this->mime,
            'size_bytes' => (int) $this->size_bytes,
            'width' => $image?->width ? (int) $image->width : null,
            'height' => $image?->height ? (int) $image->height : null,
            'duration_ms' => $avMetadata?->duration_ms ? (int) $avMetadata->duration_ms : null,
            'title' => $this->title,
            'alt' => $this->alt,
            'collection' => $this->collection,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'preview_urls' => $previewUrls,
            'download_url' => route('admin.v1.media.download', ['media' => $this->id]),
        ];
    }

    /**
     * Настроить HTTP ответ для Media.
     *
     * Устанавливает статус 201 (Created) для только что загруженных медиа-файлов.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    public function withResponse($request, $response): void
    {
        if ($this->resource instanceof Media && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }

    /**
     * Сформировать preview URLs для вариантов изображения.
     *
     * Возвращает массив URL для всех настроенных вариантов изображения
     * (thumbnail, medium, large и т.д.). Для не-изображений возвращает null.
     *
     * @return array<string, string>|null Массив [variant => URL] или null для не-изображений
     */
    private function previewUrls(): ?array
    {
        if ($this->resource->kind() !== MediaKind::Image) {
            return null;
        }

        $urls = [];

        foreach (array_keys(config('media.variants', [])) as $variant) {
            $urls[$variant] = route('admin.v1.media.preview', [
                'media' => $this->id,
                'variant' => $variant,
            ]);
        }

        return $urls;
    }
}


