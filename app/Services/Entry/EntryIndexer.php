<?php

declare(strict_types=1);

namespace App\Services\Entry;

use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис индексации данных Entry в doc_values и doc_refs.
 *
 * Извлекает значения из data_json по путям blueprint и сохраняет
 * в реляционные таблицы для быстрых запросов.
 *
 * Правила индексации:
 * - Для скалярных типов (string, int, float, bool, date, datetime, text, json) → doc_values
 * - Для ref-типов → только doc_refs (запись в doc_values запрещена)
 * - Явное сопоставление data_type → целевая колонка value_* с очисткой остальных
 * - date-тип сохраняется в value_datetime с временем 00:00:00
 * - array_index: NULL для cardinality=one, обязателен (1-based) для cardinality=many
 * - Логика проверки array_index реализована в EntryIndexer (без денормализации cardinality)
 */
class EntryIndexer
{
    /**
     * Индексировать Entry.
     *
     * Если Entry без blueprint (legacy) — индексация не выполняется.
     *
     * @param Entry $entry
     * @return void
     */
    public function index(Entry $entry): void
    {
        // Получить blueprint через PostType
        $blueprint = $entry->postType?->blueprint;

        // Если PostType без blueprint — пропустить индексацию
        if (!$blueprint) {
            Log::debug("Entry {$entry->id}: PostType без blueprint, индексация пропущена");
            return;
        }

        DB::transaction(function () use ($entry, $blueprint) {
            // 1. Удалить старые индексы
            DocValue::where('entry_id', $entry->id)->delete();
            DocRef::where('entry_id', $entry->id)->delete();

            // 2. Получить все пути blueprint (включая материализованные)
            $paths = $blueprint->paths()
                ->where('is_indexed', true)
                ->get();

            // 3. Извлечь и сохранить значения
            foreach ($paths as $path) {
                $this->indexPath($entry, $path);
            }

            // 4. Обновить версию структуры (если используется версионирование)
            // Проверяем наличие поля через Schema или try-catch
            try {
                if (isset($blueprint->structure_version) && $blueprint->structure_version) {
                    $entry->indexed_structure_version = $blueprint->structure_version;
                    $entry->saveQuietly(); // без триггера событий
                }
            } catch (\Exception $e) {
                // Поле indexed_structure_version может отсутствовать в миграции
                // Пропускаем обновление версии
            }
        });

        Log::debug("Entry {$entry->id}: индексация завершена");
    }

    /**
     * Индексировать одно поле.
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @return void
     */
    private function indexPath(Entry $entry, $path): void
    {
        // Извлечь значение из data_json по full_path
        $value = data_get($entry->data_json, $path->full_path);

        if ($value === null) {
            return; // Поле не заполнено
        }

        // Обработать в зависимости от типа
        if ($path->data_type === 'ref') {
            $this->indexRefPath($entry, $path, $value);
        } else {
            $this->indexValuePath($entry, $path, $value);
        }
    }

    /**
     * Индексировать скалярное поле (или массив скаляров).
     *
     * Явно сопоставляет data_type → целевую колонку и очищает остальные value_* поля.
     * Запрещено для ref-типов (они обрабатываются через indexRefPath).
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value
     * @return void
     * @throws \InvalidArgumentException Если path имеет data_type='ref'
     */
    private function indexValuePath(Entry $entry, $path, mixed $value): void
    {
        // Защита: ref-типы должны обрабатываться через indexRefPath
        if ($path->data_type === 'ref') {
            throw new \InvalidArgumentException(
                "Попытка записать ref-поле '{$path->full_path}' в doc_values. Используйте doc_refs."
            );
        }

        $valueField = $this->getValueFieldForType($path->data_type);

        // Базовая структура записи с явной очисткой остальных колонок
        $baseData = [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            // Явная очистка всех value_* полей
            'value_string' => null,
            'value_int' => null,
            'value_float' => null,
            'value_bool' => null,
            'value_datetime' => null,
            'value_text' => null,
            'value_json' => null,
        ];

        if ($path->cardinality === 'one') {
            // Одиночное значение: array_index = NULL
            $baseData['array_index'] = null;
            $baseData[$valueField] = $this->castValue($value, $path->data_type);

            DocValue::create($baseData);
        } else {
            // Массив значений: array_index обязателен
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $item) {
                $itemData = $baseData;
                $itemData['array_index'] = $idx + 1; // 1-based индексация
                $itemData[$valueField] = $this->castValue($item, $path->data_type);

                DocValue::create($itemData);
            }
        }
    }

    /**
     * Индексировать ref-поле (ссылка на другой Entry).
     *
     * Сохраняет только в doc_refs, не в doc_values.
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value int|array<int>
     * @return void
     */
    private function indexRefPath(Entry $entry, $path, mixed $value): void
    {
        if ($path->cardinality === 'one') {
            // Одиночная ссылка: array_index = NULL
            if (!is_int($value) && !is_numeric($value)) {
                return;
            }

            DocRef::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => null, // Для cardinality=one
                'target_entry_id' => (int) $value,
            ]);
        } else {
            // Массив ссылок: array_index обязателен
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $targetId) {
                if (!is_int($targetId) && !is_numeric($targetId)) {
                    continue;
                }

                DocRef::create([
                    'entry_id' => $entry->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1, // 1-based индексация
                    'target_entry_id' => (int) $targetId,
                ]);
            }
        }
    }

    /**
     * Получить имя колонки value_* для типа данных.
     *
     * @param string $dataType
     * @return string
     */
    private function getValueFieldForType(string $dataType): string
    {
        return match ($dataType) {
            'string' => 'value_string',
            'int' => 'value_int',
            'float' => 'value_float',
            'bool' => 'value_bool',
            'date' => 'value_datetime', // date теперь хранится в value_datetime
            'datetime' => 'value_datetime',
            'text' => 'value_text',
            'json' => 'value_json',
            default => throw new \InvalidArgumentException("Неизвестный data_type: {$dataType}"),
        };
    }

    /**
     * Привести значение к нужному типу.
     *
     * Для date-типа сохраняет DateTime с временем 00:00:00 в value_datetime.
     *
     * @param mixed $value Исходное значение
     * @param string $dataType Тип данных (string, int, float, bool, date, datetime, text, json)
     * @return mixed Приведённое значение
     */
    private function castValue(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'date' => $value instanceof \DateTimeInterface
                ? $value->setTime(0, 0, 0) // Сохраняем дату в datetime с временем 00:00:00
                : (is_string($value) ? now()->parse($value)->startOfDay() : now()->startOfDay()),
            'datetime' => $value instanceof \DateTimeInterface
                ? $value
                : (is_string($value) ? now()->parse($value) : now()),
            'text' => (string) $value,
            'json' => is_array($value) ? $value : json_decode((string) $value, true),
            default => throw new \InvalidArgumentException("Неизвестный data_type для приведения: {$dataType}"),
        };
    }
}
