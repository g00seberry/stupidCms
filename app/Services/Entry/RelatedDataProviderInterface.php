<?php

declare(strict_types=1);

namespace App\Services\Entry;

/**
 * Интерфейс для провайдеров связанных данных.
 *
 * Определяет контракт для загрузки и форматирования связанных данных,
 * которые будут включены в структуру `related` в API ответах.
 *
 * @package App\Services\Entry
 */
interface RelatedDataProviderInterface
{
    /**
     * Получить ключ для данных в структуре `related`.
     *
     * Например: 'entryData', 'mediaData', 'termData'
     *
     * @return string Ключ для структуры данных
     */
    public function getKey(): string;

    /**
     * Загрузить данные по списку ID.
     *
     * @param array<int> $ids Массив ID для загрузки
     * @return array<int, array<string, mixed>> Массив данных в формате [id => data]
     */
    public function loadData(array $ids): array;

    /**
     * Форматировать данные одного элемента.
     *
     * @param mixed $item Элемент для форматирования
     * @return array<string, mixed> Отформатированные данные
     */
    public function formatData(mixed $item): array;
}

