<?php

namespace App\Http\Controllers\Admin\V1\Concerns;

use App\Http\Resources\Admin\TermResource;
use App\Models\Entry;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait ManagesEntryTerms
{
    /**
     * @param iterable<int, Term> $terms
     */
    protected function ensureTermsAllowedForEntry(Entry $entry, iterable $terms, string $errorKey = 'term_ids'): void
    {
        $entry->loadMissing('postType');
        $allowedTaxonomies = $entry->postType?->options_json['taxonomies'] ?? [];

        if (empty($allowedTaxonomies)) {
            return;
        }

        foreach ($terms as $term) {
            if (! $term instanceof Term) {
                continue;
            }

            $term->loadMissing('taxonomy');
            $taxonomySlug = $term->taxonomy?->slug;

            if ($taxonomySlug === null || ! in_array($taxonomySlug, $allowedTaxonomies, true)) {
                throw ValidationException::withMessages([
                    $errorKey => ["Taxonomy '{$taxonomySlug}' is not allowed for the entry post type."],
                ]);
            }
        }
    }

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


