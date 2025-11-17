<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

/**
 * Резолвер правил валидации для коллекций медиа.
 *
 * Получает правила валидации (MIME, размеры, длительность, битрейт)
 * для конкретной коллекции из конфигурации. Если правила не заданы
 * для коллекции, возвращает глобальные значения.
 *
 * @package App\Domain\Media\Services
 */
class CollectionRulesResolver
{
    /**
     * Получить правила валидации для коллекции.
     *
     * @param string|null $collection Имя коллекции
     * @return array<string, mixed> Правила валидации
     */
    public function getRules(?string $collection): array
    {
        $globalRules = [
            'allowed_mimes' => config('media.allowed_mimes', []),
            'max_size_bytes' => (int) config('media.max_upload_mb', 25) * 1024 * 1024,
            'max_width' => null,
            'max_height' => null,
            'max_duration_ms' => null,
            'max_bitrate_kbps' => null,
        ];

        if ($collection === null || $collection === '') {
            return $globalRules;
        }

        $collectionRules = config("media.collections.{$collection}", []);

        if (! is_array($collectionRules) || empty($collectionRules)) {
            return $globalRules;
        }

        // Объединяем правила коллекции с глобальными
        return array_merge($globalRules, array_filter($collectionRules, fn ($value) => $value !== null));
    }

    /**
     * Получить разрешённые MIME-типы для коллекции.
     *
     * @param string|null $collection Имя коллекции
     * @return array<string> Массив MIME-типов
     */
    public function getAllowedMimes(?string $collection): array
    {
        $rules = $this->getRules($collection);

        return $rules['allowed_mimes'] ?? config('media.allowed_mimes', []);
    }

    /**
     * Получить максимальный размер файла для коллекции.
     *
     * @param string|null $collection Имя коллекции
     * @return int Максимальный размер в байтах
     */
    public function getMaxSizeBytes(?string $collection): int
    {
        $rules = $this->getRules($collection);

        return $rules['max_size_bytes'] ?? ((int) config('media.max_upload_mb', 25) * 1024 * 1024);
    }
}

