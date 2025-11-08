<?php

declare(strict_types=1);

namespace App\Domain\Search\Transformers;

use App\Models\Entry;
use App\Models\Term;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class EntryToSearchDoc
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Entry $entry): array
    {
        $data = $entry->getAttribute('data_json') ?? [];

        $bodyHtml = $this->extractBody($data);
        $bodyPlain = $this->normalizeWhitespace(strip_tags($bodyHtml));
        $excerpt = $this->makeExcerpt($data, $bodyPlain);
        $boost = $this->extractBoost($data);

        return array_filter([
            'id' => (string) $entry->getKey(),
            'post_type' => (string) $entry->postType?->slug,
            'slug' => (string) $entry->slug,
            'title' => (string) $entry->title,
            'excerpt' => $excerpt,
            'body_plain' => $bodyPlain,
            'terms' => $this->mapTerms($entry),
            'published_at' => $entry->published_at?->toIso8601String(),
            'boost' => $boost,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractBody(array $data): string
    {
        $body = Arr::get($data, 'body', Arr::get($data, 'content', ''));

        return is_string($body) ? $body : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function makeExcerpt(array $data, string $bodyPlain): string
    {
        $excerpt = Arr::get($data, 'excerpt');

        if (is_string($excerpt) && $excerpt !== '') {
            return $this->normalizeWhitespace(strip_tags($excerpt));
        }

        return Str::limit($bodyPlain, 240, '...');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractBoost(array $data): ?float
    {
        $boost = Arr::get($data, 'search_boost');

        if (is_numeric($boost)) {
            return (float) $boost;
        }

        return null;
    }

    /**
     * @return list<array{taxonomy: string, slug: string}>
     */
    private function mapTerms(Entry $entry): array
    {
        if (! $entry->relationLoaded('terms')) {
            $entry->loadMissing('terms.taxonomy');
        }

        return $entry->terms
            ->map(function (Term $term): ?array {
                $taxonomy = $term->taxonomy?->slug;
                if ($taxonomy === null) {
                    return null;
                }

                return [
                    'taxonomy' => (string) $taxonomy,
                    'slug' => (string) $term->slug,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeWhitespace(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }
}


