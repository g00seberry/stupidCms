<?php

namespace App\Support\Publishing;

use App\Models\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PublishingService
{
    /**
     * Применяет правила публикации и валидирует инварианты
     *
     * @param array $payload Данные для сохранения
     * @param Entry|null $existing Существующая запись (для обновления)
     * @return array Обработанный payload
     * @throws ValidationException
     */
    public function applyAndValidate(array $payload, ?Entry $existing = null): array
    {
        $nowUtc = Carbon::now('UTC');

        $newStatus = $payload['status'] ?? $existing?->status ?? 'draft';
        $hasPublishedAtKey = array_key_exists('published_at', $payload);

        // Автозаполняем ТОЛЬКО если:
        // - создаём published без published_at;
        // - или переводим draft -> published без published_at;
        // - или у существующей записи published_at ещё пустая.
        $isPublishingTransition = $existing && $existing->status === 'draft' && $newStatus === 'published';

        if ($newStatus === 'published') {
            if (
                (!$existing && !$hasPublishedAtKey) ||
                ($isPublishingTransition && !$hasPublishedAtKey) ||
                ($existing && $existing->published_at === null && !$hasPublishedAtKey)
            ) {
                $payload['published_at'] = $nowUtc;
            }
        }

        // Валидация инварианта, если дата в payload есть (или будет сразу после автозаполнения)
        if ($newStatus === 'published' && !empty($payload['published_at'])) {
            $publishedAt = Carbon::parse($payload['published_at'], 'UTC');

            if ($publishedAt->gt($nowUtc)) {
                throw ValidationException::withMessages([
                    'published_at' => __('validation.published_at_not_in_future', [], 'ru'),
                ]);
            }
        }

        return $payload;
    }
}

