<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Entry;
use App\Services\Entry\EntryRelatedDataFormatter;
use Illuminate\Pagination\AbstractPaginator;

/**
 * API Resource Collection для списка Entry в админ-панели.
 *
 * Форматирует коллекцию записей с поддержкой пагинации.
 * Оптимизирует загрузку связанных данных: собирает все ref-значения
 * и media-значения из всех Entry и загружает их одним запросом.
 *
 * @package App\Http\Resources\Admin
 */
class EntryCollection extends AdminResourceCollection
{
    /**
     * Класс ресурса для элементов коллекции.
     *
     * @var string
     */
    public $collects = EntryResource::class;

    /**
     * Преобразовать коллекцию ресурсов в массив.
     *
     * Собирает все ref-значения и media-значения из всех Entry в коллекции,
     * загружает связанные данные одним запросом и добавляет
     * их в структуру ответа на уровне коллекции.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с ключом 'data' и опционально 'related'
     */
    public function toArray($request): array
    {
        // Собрать все ref-значения из всех Entry
        $relatedData = $this->collectRelatedData();

        $result = [
            'data' => $this->collection,
        ];

        // Добавляем related данные только если они не пустые
        if (!empty($relatedData)) {
            $formatter = app(EntryRelatedDataFormatter::class);
            $result['related'] = $formatter->formatRelatedData($relatedData);
        }

        return $result;
    }

    /**
     * Собрать связанные данные из всех Entry в коллекции.
     *
     * @return array<string, array<string, array<string, mixed>>> Структура related данных
     */
    private function collectRelatedData(): array
    {
        // Извлечь Entry из ресурсов коллекции
        $entries = $this->collection
            ->map(fn($resource) => $resource->resource)
            ->filter(fn($item) => $item instanceof Entry);

        if ($entries->isEmpty()) {
            return [];
        }

        $formatter = app(EntryRelatedDataFormatter::class);
        return $formatter->loadRelatedDataForCollection($entries);
    }

    /**
     * Настроить информацию о пагинации для обеспечения консистентности типов.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param array<string, mixed> $paginated Пагинированные данные
     * @param array<string, mixed> $default Значения по умолчанию
     * @return array<string, mixed> Структура пагинации
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        return $this->buildPagination($default);
    }
}

