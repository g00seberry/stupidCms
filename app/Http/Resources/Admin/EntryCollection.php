<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Entry;
use App\Services\Entry\EntryRefExtractor;
use App\Services\Entry\EntryRelatedDataLoader;
use Illuminate\Pagination\AbstractPaginator;

/**
 * API Resource Collection для списка Entry в админ-панели.
 *
 * Форматирует коллекцию записей с поддержкой пагинации.
 * Оптимизирует загрузку связанных данных: собирает все ref-значения
 * из всех Entry и загружает их одним запросом.
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
     * Собирает все ref-значения из всех Entry в коллекции,
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
            // Преобразуем related данные в объект для гарантированной сериализации как объект в JSON
            $result['related'] = $this->transformRelatedToObject($relatedData);
        }

        return $result;
    }

    /**
     * Собрать связанные данные из всех Entry в коллекции.
     *
     * Извлекает все ref-значения из всех Entry и загружает
     * связанные данные одним запросом для оптимизации.
     *
     * @return array<string, array<string, array<string, mixed>>> Структура related данных
     */
    private function collectRelatedData(): array
    {
        $refExtractor = app(EntryRefExtractor::class);
        $allEntryIds = [];

        // Собрать все ref-значения из всех Entry
        foreach ($this->collection as $entry) {
            if (!($entry instanceof Entry)) {
                continue;
            }

            $entryIds = $refExtractor->extractRefEntryIds($entry);
            $allEntryIds = array_merge($allEntryIds, $entryIds);
        }

        // Убрать дубликаты
        $allEntryIds = array_values(array_unique(array_filter($allEntryIds)));

        if (empty($allEntryIds)) {
            return [];
        }

        // Загрузить связанные данные одним запросом
        $relatedDataLoader = app(EntryRelatedDataLoader::class);
        return $relatedDataLoader->loadRelatedData($allEntryIds);
    }

    /**
     * Преобразовать related данные в объект для гарантированной сериализации как объект в JSON.
     *
     * Преобразует структуру related данных в объект stdClass, чтобы гарантировать,
     * что entryData будет объектом, а не массивом в JSON.
     *
     * @param array<string, array<string, array<string, mixed>>> $relatedData Related данные
     * @return \stdClass Объект с related данными
     */
    private function transformRelatedToObject(array $relatedData): \stdClass
    {
        $object = new \stdClass();
        
        foreach ($relatedData as $key => $value) {
            // Для entryData создаем объект с ключами-строками
            if ($key === 'entryData' && is_array($value)) {
                $entryDataObject = new \stdClass();
                foreach ($value as $entryId => $entryData) {
                    $entryDataObject->{$entryId} = (object) $entryData;
                }
                $object->{$key} = $entryDataObject;
            } else {
                $object->{$key} = is_array($value) ? (object) $value : $value;
            }
        }
        
        return $object;
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

