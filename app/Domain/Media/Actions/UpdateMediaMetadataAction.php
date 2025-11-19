<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Models\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * CQRS-действие: обновление метаданных медиа (title, alt).
 */
final class UpdateMediaMetadataAction
{
    /**
     * Обновить метаданные медиа и вернуть актуальную модель.
     *
     * @param string $mediaId ULID медиа
     * @param array<string, mixed> $attributes Валидированные поля (title, alt)
     * @return \App\Models\Media
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function execute(string $mediaId, array $attributes): Media
    {
        $media = Media::withTrashed()->find($mediaId);
        if (! $media) {
            throw new ModelNotFoundException("Media {$mediaId} not found.");
        }

        $media->fill($attributes);
        $media->save();

        return $media->fresh();
    }
}


