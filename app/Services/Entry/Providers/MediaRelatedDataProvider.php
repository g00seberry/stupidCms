<?php

declare(strict_types=1);

namespace App\Services\Entry\Providers;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Entry\RelatedDataProviderInterface;

/**
 * Провайдер связанных данных для Media.
 *
 * Загружает и форматирует данные о связанных Media для включения
 * в структуру `related.mediaData` в API ответах.
 *
 * @package App\Services\Entry\Providers
 */
class MediaRelatedDataProvider implements RelatedDataProviderInterface
{
    /**
     * Получить ключ для данных в структуре `related`.
     *
     * @return string
     */
    public function getKey(): string
    {
        return 'mediaData';
    }

    /**
     * Загрузить данные Media по списку ULID.
     *
     * Исключает удаленные записи (deleted_at IS NULL).
     * Загружает связи для MediaResource (image для изображений, avMetadata для видео/аудио).
     *
     * @param array<int|string> $ids Массив ULID Media для загрузки
     * @return array<string, array<string, mixed>> Массив данных в формате [mediaId => data]
     */
    public function loadData(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Загрузить Media с связями, исключая удаленные
        $mediaItems = Media::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->with(['image', 'avMetadata'])
            ->get();

        $result = [];

        foreach ($mediaItems as $media) {
            $result[$media->id] = $this->formatData($media);
        }

        return $result;
    }

    /**
     * Форматировать данные одного Media.
     *
     * Использует MediaResource для получения полных данных Media.
     *
     * @param Media $media Media для форматирования
     * @return array<string, mixed> Отформатированные данные
     */
    public function formatData(mixed $media): array
    {
        if (!($media instanceof Media)) {
            throw new \InvalidArgumentException(
                'MediaRelatedDataProvider::formatData() expects Media instance'
            );
        }

        $resource = MediaResource::make($media);
        return $resource->toArray(request());
    }
}

