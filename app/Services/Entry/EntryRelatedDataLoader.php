<?php

declare(strict_types=1);

namespace App\Services\Entry;

/**
 * Сервис загрузки связанных данных для Entry.
 *
 * Использует провайдеры для загрузки различных типов связанных данных
 * и объединяет их в структуру `related` для API ответов.
 *
 * @package App\Services\Entry
 */
class EntryRelatedDataLoader
{
    /**
     * @param RelatedDataProviderRegistry $registry Регистр провайдеров связанных данных
     */
    public function __construct(
        private readonly RelatedDataProviderRegistry $registry
    ) {}

    /**
     * Загрузить связанные данные по списку ID.
     *
     * Использует все зарегистрированные провайдеры для загрузки данных
     * и объединяет их в структуру `related`.
     * Каждый провайдер получает весь массив ID и сам фильтрует нужные ему типы
     * через whereIn() (EntryRelatedDataProvider работает с int, MediaRelatedDataProvider - со string).
     * Преобразует числовые ключи в строки для гарантированной сериализации как объект в JSON.
     *
     * @param array<int|string> $ids Массив ID (может содержать int для Entry и/или string для Media)
     * @return array<string, array<string, array<string, mixed>>> Структура related данных
     */
    public function loadRelatedData(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = [];

        // Загрузить данные через все зарегистрированные провайдеры
        foreach ($this->registry->getAllProviders() as $provider) {
            $key = $provider->getKey();
            $data = $provider->loadData($ids);
            
            // Добавляем данные только если они не пустые
            if (!empty($data)) {
                // Преобразуем числовые ключи в строки для гарантированной сериализации как объект в JSON
                $result[$key] = $this->normalizeKeysToStrings($data);
            }
        }

        return $result;
    }

    /**
     * Нормализовать ключи массива в строки.
     *
     * Преобразует числовые ключи в строки, чтобы гарантировать
     * сериализацию как объект в JSON, а не как массив.
     *
     * @param array<int|string, mixed> $data Данные с числовыми или строковыми ключами
     * @return array<string, mixed> Данные со строковыми ключами
     */
    private function normalizeKeysToStrings(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $stringKey = (string) $key;
            $result[$stringKey] = $value;
        }
        return $result;
    }
}

