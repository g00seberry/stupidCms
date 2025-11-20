<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Path;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait для запросов к индексированным данным Entry.
 *
 * Предоставляет scopes для фильтрации Entry по индексированным полям.
 */
trait HasDocumentData
{
    /**
     * Фильтровать Entry по значению индексированного поля.
     *
     * @param Builder $query
     * @param string $fullPath Полный путь поля ('author.name', 'tags')
     * @param string $operator Оператор ('=', '>', '<', 'like', etc.)
     * @param mixed $value Значение для сравнения
     * @return Builder
     *
     * @example Entry::wherePath('author.name', '=', 'John')->get()
     * @example Entry::wherePath('price', '>', 100)->get()
     */
    public function scopeWherePath(Builder $query, string $fullPath, string $operator, mixed $value): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath, $operator, $value) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            });

            // Определить колонку value_* по типу значения
            $valueField = $this->detectValueField($value);
            $q->where($valueField, $operator, $value);
        });
    }

    /**
     * Фильтровать по значениям из списка (IN).
     *
     * @param Builder $query
     * @param string $fullPath
     * @param array $values
     * @return Builder
     *
     * @example Entry::wherePathIn('category', ['tech', 'science'])->get()
     */
    public function scopeWherePathIn(Builder $query, string $fullPath, array $values): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath, $values) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));

            if (empty($values)) {
                return;
            }

            $valueField = $this->detectValueField($values[0]);
            $q->whereIn($valueField, $values);
        });
    }

    /**
     * Фильтровать Entry, у которых есть ссылка на указанный Entry.
     *
     * @param Builder $query
     * @param string $fullPath Полный путь ref-поля ('article', 'relatedArticles')
     * @param int $targetEntryId ID целевого Entry
     * @return Builder
     *
     * @example Entry::whereRef('relatedArticles', 42)->get()
     */
    public function scopeWhereRef(Builder $query, string $fullPath, int $targetEntryId): Builder
    {
        return $query->whereHas('docRefs', function ($q) use ($fullPath, $targetEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('target_entry_id', $targetEntryId);
        });
    }

    /**
     * Фильтровать Entry, на которые ссылается указанный Entry (обратный запрос).
     *
     * @param Builder $query
     * @param string $fullPath
     * @param int $ownerEntryId
     * @return Builder
     *
     * @example Entry::referencedBy('relatedArticles', 1)->get()
     */
    public function scopeReferencedBy(Builder $query, string $fullPath, int $ownerEntryId): Builder
    {
        return $query->whereHas('docRefsIncoming', function ($q) use ($fullPath, $ownerEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('entry_id', $ownerEntryId);
        });
    }

    /**
     * Фильтровать Entry с любым значением в указанном поле (NOT NULL).
     *
     * @param Builder $query
     * @param string $fullPath
     * @return Builder
     *
     * @example Entry::wherePathExists('author.bio')->get()
     */
    public function scopeWherePathExists(Builder $query, string $fullPath): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }

    /**
     * Фильтровать Entry, у которых поле НЕ заполнено (NULL).
     *
     * @param Builder $query
     * @param string $fullPath
     * @return Builder
     *
     * @example Entry::wherePathMissing('author.bio')->get()
     */
    public function scopeWherePathMissing(Builder $query, string $fullPath): Builder
    {
        return $query->whereDoesntHave('docValues', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }

    /**
     * Сортировать по индексированному полю.
     *
     * @param Builder $query
     * @param string $fullPath
     * @param string $direction 'asc' | 'desc'
     * @return Builder
     *
     * @example Entry::orderByPath('price', 'desc')->get()
     */
    public function scopeOrderByPath(Builder $query, string $fullPath, string $direction = 'asc'): Builder
    {
        return $query
            ->leftJoin('doc_values as dv_sort', function ($join) use ($fullPath) {
                $join->on('entries.id', '=', 'dv_sort.entry_id')
                    ->whereIn('dv_sort.path_id', function ($subQuery) use ($fullPath) {
                        $subQuery->select('id')
                            ->from('paths')
                            ->where('full_path', $fullPath);
                    });
            })
            ->orderBy('dv_sort.value_string', $direction)
            ->select('entries.*');
    }

    /**
     * Определить колонку value_* по типу значения.
     *
     * @param mixed $value
     * @return string
     */
    private function detectValueField(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'value_int',
            is_float($value) => 'value_float',
            is_bool($value) => 'value_bool',
            $value instanceof \DateTimeInterface => 'value_datetime',
            default => 'value_string',
        };
    }
}
