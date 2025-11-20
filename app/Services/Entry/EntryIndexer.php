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
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value
     * @return void
     */
    private function indexValuePath(Entry $entry, $path, mixed $value): void
    {
        $valueField = $this->getValueFieldForType($path->data_type);

        if ($path->cardinality === 'one') {
            // Одиночное значение
            DocValue::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => 0,
                $valueField => $this->castValue($value, $path->data_type),
            ]);
        } else {
            // Массив значений
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $item) {
                DocValue::create([
                    'entry_id' => $entry->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1, // 1-based индексация
                    $valueField => $this->castValue($item, $path->data_type),
                ]);
            }
        }
    }

    /**
     * Индексировать ref-поле (ссылка на другой Entry).
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value int|array<int>
     * @return void
     */
    private function indexRefPath(Entry $entry, $path, mixed $value): void
    {
        if ($path->cardinality === 'one') {
            // Одиночная ссылка
            if (!is_int($value) && !is_numeric($value)) {
                return;
            }

            DocRef::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => 0,
                'target_entry_id' => (int) $value,
            ]);
        } else {
            // Массив ссылок
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
                    'array_index' => $idx + 1,
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
            'date' => 'value_date',
            'datetime' => 'value_datetime',
            'text' => 'value_text',
            'json' => 'value_json',
            default => throw new \InvalidArgumentException("Неизвестный data_type: {$dataType}"),
        };
    }

    /**
     * Привести значение к нужному типу.
     *
     * @param mixed $value
     * @param string $dataType
     * @return mixed
     */
    private function castValue(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value,
            'datetime' => $value instanceof \DateTimeInterface
                ? $value
                : now()->parse($value),
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => (string) $value,
        };
    }
}
