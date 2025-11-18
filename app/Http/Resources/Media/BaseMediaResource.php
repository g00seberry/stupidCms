<?php

declare(strict_types=1);

namespace App\Http\Resources\Media;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый абстрактный ресурс для медиа-файлов.
 *
 * Содержит общие поля для всех типов медиа:
 * id, kind, name, ext, mime, size_bytes, title, alt, collection, timestamps, url.
 *
 * @package App\Http\Resources\Media
 * @property-read \App\Models\Media $resource Медиа-файл
 */
abstract class BaseMediaResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает базовые поля медиа-файла. Специализированные ресурсы
     * должны переопределить этот метод и добавить специфичные поля.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с базовыми полями медиа-файла
     */
    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        return [
            'id' => $media->id,
            'kind' => $media->kind()->value,
            'name' => $media->original_name,
            'ext' => $media->ext,
            'mime' => $media->mime,
            'size_bytes' => (int) $media->size_bytes,
            'title' => $media->title,
            'alt' => $media->alt,
            'collection' => $media->collection,
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
            'deleted_at' => $media->deleted_at?->toIso8601String(),
            'url' => route('api.v1.media.show', ['id' => $media->id]),
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
        /** @var Media $media */
        $media = $this->resource;

        if ($media instanceof Media && $media->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

