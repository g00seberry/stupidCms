<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1\Concerns;

use App\Http\Resources\Admin\TermResource;
use App\Models\Entry;
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
     * Формирует структуру с термами, сгруппированными по ID таксономий.
     *
     * @param \App\Models\Entry $entry Запись с загруженными термами
     * @return array{entry_id: int, terms: array<int, array>, terms_by_taxonomy: array<int, array>} Payload ответа
     */
    protected function buildEntryTermsPayload(Entry $entry): array
    {
        $entry->load('terms.taxonomy');

        $terms = TermResource::collection($entry->terms)->resolve();
        $grouped = collect($entry->terms)
            ->groupBy(fn (Term $term) => $term->taxonomy_id)
            ->map(fn (Collection $items) => TermResource::collection($items)->resolve())
            ->map(fn (array $items) => collect($items)->map(function (array $term) {
                $copy = $term;
                unset($copy['taxonomy']);
                return $copy;
            })->values()->toArray())
            ->toArray();

        return [
            'entry_id' => $entry->id,
            'terms' => array_values($terms),
            'terms_by_taxonomy' => $grouped,
        ];
    }
}


