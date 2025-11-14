<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1\Concerns;

use App\Http\Resources\Admin\TaxonomyResource;
use App\Http\Resources\Admin\TermResource;
use App\Models\Entry;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Trait для управления термами записей.
 *
 * Предоставляет вспомогательные методы для валидации и форматирования
 * термов, привязанных к записям.
 *
 * @package App\Http\Controllers\Admin\V1\Concerns
 */
trait ManagesEntryTerms
{
    /**
     * Проверить, что термы разрешены для типа записи.
     *
     * Валидирует, что все термы принадлежат таксономиям, разрешённым
     * для типа записи (из options_json['taxonomies']).
     *
     * @param \App\Models\Entry $entry Запись
     * @param iterable<int, \App\Models\Term> $terms Список термов для проверки
     * @param string $errorKey Ключ ошибки для ValidationException
     * @return void
     * @throws \Illuminate\Validation\ValidationException Если терм не разрешён для типа записи
     */
    protected function ensureTermsAllowedForEntry(Entry $entry, iterable $terms, string $errorKey = 'term_ids'): void
    {
        $entry->loadMissing('postType');
        $postType = $entry->postType;

        if ($postType === null) {
            return;
        }

        $options = $postType->options_json;
        $allowedTaxonomies = $options->getAllowedTaxonomies();

        if (empty($allowedTaxonomies)) {
            return;
        }

        foreach ($terms as $term) {
            if (! $term instanceof Term) {
                continue;
            }

            $taxonomyId = $term->taxonomy_id;

            if ($taxonomyId === null || ! $options->isTaxonomyAllowed($taxonomyId)) {
                throw ValidationException::withMessages([
                    $errorKey => ["Taxonomy with id '{$taxonomyId}' is not allowed for the entry post type."],
                ]);
            }
        }
    }

    /**
     * Построить payload для ответа с термами записи.
     *
     * Формирует структуру с термами, сгруппированными по таксономиям.
     * Возвращает массив объектов, где каждый объект содержит таксономию и её термы.
     * Все разрешённые таксономии для типа записи возвращаются, даже если к ним не прикреплены термы.
     *
     * @param \App\Models\Entry $entry Запись с загруженными термами
     * @return array{entry_id: int, terms_by_taxonomy: array<int, array{taxonomy: array, terms: array}>} Payload ответа
     */
    protected function buildEntryTermsPayload(Entry $entry): array
    {
        $entry->loadMissing(['terms', 'postType']);

        // Получаем разрешённые таксономии для типа записи
        $allowedTaxonomyIds = [];
        if ($entry->postType !== null) {
            $allowedTaxonomyIds = $entry->postType->options_json->getAllowedTaxonomies();
        }

        // Если список пуст, все таксономии разрешены - загружаем все
        if (empty($allowedTaxonomyIds)) {
            $taxonomies = Taxonomy::all();
        } else {
            $taxonomies = Taxonomy::whereIn('id', $allowedTaxonomyIds)->get();
        }

        // Группируем существующие термы по таксономиям
        $termsByTaxonomyId = collect($entry->terms)
            ->groupBy(fn (Term $term) => $term->taxonomy_id)
            ->map(function (Collection $terms) {
                $termsData = TermResource::collection($terms)->resolve();

                // Убираем поле taxonomy из каждого терма, так как оно уже в родительском объекте
                return collect($termsData)->map(function (array $term) {
                    $copy = $term;
                    unset($copy['taxonomy']);
                    return $copy;
                })->values()->toArray();
            });

        // Формируем результат: для каждой разрешённой таксономии создаём объект
        $termsByTaxonomy = $taxonomies
            ->map(function (Taxonomy $taxonomy) use ($termsByTaxonomyId) {
                $taxonomyData = (new TaxonomyResource($taxonomy))->resolve();
                $termsData = $termsByTaxonomyId->get($taxonomy->id, []);

                return [
                    'taxonomy' => $taxonomyData,
                    'terms' => $termsData,
                ];
            })
            ->values()
            ->toArray();

        return [
            'entry_id' => $entry->id,
            'terms_by_taxonomy' => $termsByTaxonomy,
        ];
    }
}


