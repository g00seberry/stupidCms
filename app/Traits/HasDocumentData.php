<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Blueprint;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Path;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Трейт для индексации JSON документов в таблицах doc_values и doc_refs.
 *
 * Автоматически индексирует поля из data_json при сохранении модели.
 */
trait HasDocumentData
{
    /**
     * Регистрация обработчиков событий модели.
     */
    protected static function bootHasDocumentData(): void
    {
        static::saved(function ($entry) {
            $entry->syncDocumentIndex();
        });
    }

    /**
     * Связь с DocValue.
     */
    public function values(): HasMany
    {
        return $this->hasMany(DocValue::class, 'entry_id');
    }

    /**
     * Связь с DocRef.
     */
    public function refs(): HasMany
    {
        return $this->hasMany(DocRef::class, 'entry_id');
    }

    /**
     * Связь с Blueprint.
     */
    abstract public function blueprint();

    /**
     * Синхронизировать индексы документа.
     *
     * Удаляет старые значения и создаёт новые на основе текущего data_json.
     */
    public function syncDocumentIndex(): void
    {
        if (!$this->blueprint_id) {
            return;
        }

        /** @var Blueprint $blueprint */
        $blueprint = $this->blueprint()->first();

        if (!$blueprint) {
            return;
        }

        // Получить все индексируемые Paths
        $indexedPaths = $blueprint->getAllPaths()->where('is_indexed', true);

        // Удалить старые индексы (FK CASCADE удалит всё автоматически, но можно и вручную)
        // Оставляем только FK CASCADE для упрощения
        $this->values()->delete();
        $this->refs()->delete();

        // Пройтись по каждому индексируемому пути
        foreach ($indexedPaths as $path) {
            $value = Arr::get($this->data_json ?? [], $path->full_path);

            if ($value === null) {
                continue;
            }

            if ($path->isRef()) {
                $this->syncRefPath($path, $value);
            } else {
                $this->syncScalarPath($path, $value);
            }
        }
    }

    /**
     * Индексировать скалярное поле.
     *
     * @param Path $path
     * @param mixed $value
     */
    private function syncScalarPath(Path $path, mixed $value): void
    {
        $valueField = $this->getValueFieldForType($path->data_type);

        if ($path->cardinality === 'one') {
            DocValue::create([
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'idx' => 0,
                $valueField => $value,
                'created_at' => now(),
            ]);
        } else {
            // many
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $item) {
                DocValue::create([
                    'entry_id' => $this->id,
                    'path_id' => $path->id,
                    'idx' => $idx,
                    $valueField => $item,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Индексировать поле-ссылку.
     *
     * @param Path $path
     * @param mixed $value
     */
    private function syncRefPath(Path $path, mixed $value): void
    {
        if ($path->cardinality === 'one') {
            if (!is_numeric($value)) {
                return;
            }

            DocRef::create([
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'idx' => 0,
                'target_entry_id' => (int) $value,
                'created_at' => now(),
            ]);
        } else {
            // many
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $targetId) {
                if (!is_numeric($targetId)) {
                    continue;
                }

                DocRef::create([
                    'entry_id' => $this->id,
                    'path_id' => $path->id,
                    'idx' => $idx,
                    'target_entry_id' => (int) $targetId,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Получить имя поля value_* для типа данных.
     *
     * @param string $dataType
     * @return string
     */
    private function getValueFieldForType(string $dataType): string
    {
        return match($dataType) {
            'string' => 'value_string',
            'int' => 'value_int',
            'float' => 'value_float',
            'bool' => 'value_bool',
            'text' => 'value_text',
            'json' => 'value_json',
            default => 'value_string',
        };
    }

    /**
     * Скоуп: фильтрация по скалярному полю.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @param string $op
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePath($query, string $fullPath, string $op, $value)
    {
        return $query->whereHas('values', function ($q) use ($fullPath, $op, $value) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            })
            ->where('value_string', $op, $value); // Упрощённо: только string
        });
    }

    /**
     * Скоуп: фильтрация по скалярному полю с явным типом.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @param string $dataType
     * @param string $op
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePathTyped($query, string $fullPath, string $dataType, string $op, $value)
    {
        $valueField = $this->getValueFieldForType($dataType);

        return $query->whereHas('values', function ($q) use ($fullPath, $valueField, $op, $value) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            })
            ->where($valueField, $op, $value);
        });
    }

    /**
     * Скоуп: фильтрация по ссылке.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @param int $targetEntryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereRef($query, string $fullPath, int $targetEntryId)
    {
        return $query->whereHas('refs', function ($q) use ($fullPath, $targetEntryId) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            })
            ->where('target_entry_id', $targetEntryId);
        });
    }
}

