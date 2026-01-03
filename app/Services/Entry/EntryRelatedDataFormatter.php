<?php

declare(strict_types=1);

namespace App\Services\Entry;

use App\Models\Entry;
use Illuminate\Support\Collection;

/**
 * Сервис форматирования связанных данных для Entry.
 *
 * Инкапсулирует логику загрузки и форматирования related данных
 * для Entry и коллекций Entry.
 *
 * @package App\Services\Entry
 */
class EntryRelatedDataFormatter
{
    /**
     * @param EntryRefExtractor $refExtractor Извлекатель ref-значений
     * @param EntryMediaExtractor $mediaExtractor Извлекатель media-значений
     * @param EntryRelatedDataLoader $dataLoader Загрузчик связанных данных
     */
    public function __construct(
        private readonly EntryRefExtractor $refExtractor,
        private readonly EntryMediaExtractor $mediaExtractor,
        private readonly EntryRelatedDataLoader $dataLoader
    ) {}

    /**
     * Загрузить связанные данные для одного Entry.
     *
     * Извлекает ref-значения и media-значения из data_json Entry
     * и загружает связанные данные.
     *
     * @param Entry $entry Entry для обработки
     * @return array<string, array<string, array<string, mixed>>> Структура related данных
     */
    public function loadRelatedDataForEntry(Entry $entry): array
    {
        // Извлечь ref-значения
        $entryIds = $this->refExtractor->extractRefEntryIds($entry);

        // Извлечь media-значения
        $mediaIds = $this->mediaExtractor->extractMediaIds($entry);

        $result = [];

        // Загрузить данные Entry отдельно
        if (!empty($entryIds)) {
            $entryData = $this->dataLoader->loadRelatedData($entryIds);
            $result = array_merge($result, $entryData);
        }

        // Загрузить данные Media отдельно
        if (!empty($mediaIds)) {
            $mediaData = $this->dataLoader->loadRelatedData($mediaIds);
            $result = array_merge($result, $mediaData);
        }

        return $result;
    }

    /**
     * Загрузить связанные данные для коллекции Entry.
     *
     * Собирает все ref-значения и media-значения из всех Entry в коллекции
     * и загружает связанные данные одним запросом для оптимизации.
     *
     * @param Collection<int, Entry> $entries Коллекция Entry
     * @return array<string, array<string, array<string, mixed>>> Структура related данных
     */
    public function loadRelatedDataForCollection(Collection $entries): array
    {
        $allEntryIds = [];
        $allMediaIds = [];

        // Собрать все ref-значения и media-значения из всех Entry
        foreach ($entries as $entry) {
            $entryIds = $this->refExtractor->extractRefEntryIds($entry);
            $allEntryIds = array_merge($allEntryIds, $entryIds);

            $mediaIds = $this->mediaExtractor->extractMediaIds($entry);
            $allMediaIds = array_merge($allMediaIds, $mediaIds);
        }

        // Убрать дубликаты
        $allEntryIds = array_values(array_unique(array_filter($allEntryIds)));
        $allMediaIds = array_values(array_unique(array_filter($allMediaIds)));

        $result = [];

        // Загрузить данные Entry отдельно
        if (!empty($allEntryIds)) {
            $entryData = $this->dataLoader->loadRelatedData($allEntryIds);
            $result = array_merge($result, $entryData);
        }

        // Загрузить данные Media отдельно
        if (!empty($allMediaIds)) {
            $mediaData = $this->dataLoader->loadRelatedData($allMediaIds);
            $result = array_merge($result, $mediaData);
        }

        return $result;
    }

    /**
     * Преобразовать related данные в объект для гарантированной сериализации как объект в JSON.
     *
     * Преобразует структуру related данных в объект stdClass, чтобы гарантировать,
     * что entryData и mediaData будут объектами, а не массивами в JSON.
     *
     * @param array<string, array<string, array<string, mixed>>> $relatedData Related данные
     * @return \stdClass Объект с related данными
     */
    public function formatRelatedData(array $relatedData): \stdClass
    {
        $object = new \stdClass();

        foreach ($relatedData as $key => $value) {
            // Для entryData и mediaData создаем объекты с ключами-строками
            if (($key === 'entryData' || $key === 'mediaData') && is_array($value)) {
                $dataObject = new \stdClass();
                foreach ($value as $id => $data) {
                    $dataObject->{$id} = (object) $data;
                }
                $object->{$key} = $dataObject;
            } else {
                $object->{$key} = is_array($value) ? (object) $value : $value;
            }
        }

        return $object;
    }
}

