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

            $term->loadMissing('taxonomy');
            $taxonomySlug = $term->taxonomy?->slug;

            if ($taxonomySlug === null || ! $options->isTaxonomyAllowed($taxonomySlug)) {
                throw ValidationException::withMessages([
                    $errorKey => ["Taxonomy '{$taxonomySlug}' is not allowed for the entry post type."],
                ]);
            }
        }
    }

    /**
     * Построить payload для ответа с термами записи.
     *
     * Формирует структуру с термами, сгруппированными по таксономиям.
     *
     * @param \App\Models\Entry $entry Запись с загруженными термами
     * @return array{entry_id: int, terms: array<int, array>, terms_by_taxonomy: array<string, array>} Payload ответа
     */
    protected function buildEntryTermsPayload(Entry $entry): array
    {
        $entry->load('terms.taxonomy');

        $terms = TermResource::collection($entry->terms)->resolve();
        $grouped = collect($terms)
            ->groupBy(fn ($term) => $term['taxonomy'] ?? 'unknown')
            ->map(fn (Collection $items) => $items->map(function (array $term) {
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


