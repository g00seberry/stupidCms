<?php

declare(strict_types=1);

namespace App\Domain\Search\Transformers;

use App\Models\Entry;
use App\Models\Term;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Трансформер Entry в документ для поискового индекса.
 *
 * Преобразует Entry в структуру документа для Elasticsearch:
 * извлекает текст из data_json, нормализует пробелы, формирует excerpt.
 *
 * @package App\Domain\Search\Transformers
 */
final class EntryToSearchDoc
{
    /**
     * Трансформировать Entry в документ для поискового индекса.
     *
     * Извлекает данные из Entry и data_json, нормализует текст,
     * формирует excerpt и маппит термы.
     *
     * @param \App\Models\Entry $entry Запись для трансформации
     * @return array<string, mixed> Документ для индексации
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
     * Извлечь тело контента из data_json.
     *
     * Ищет поле 'body' или 'content' в data_json.
     *
     * @param array<string, mixed> $data Данные из data_json
     * @return string Тело контента (HTML)
     */
    private function extractBody(array $data): string
    {
        $body = Arr::get($data, 'body', Arr::get($data, 'content', ''));

        return is_string($body) ? $body : '';
    }

    /**
     * Сформировать excerpt (краткое описание).
     *
     * Использует excerpt из data_json, если есть, иначе обрезает body_plain до 240 символов.
     *
     * @param array<string, mixed> $data Данные из data_json
     * @param string $bodyPlain Очищенный текст тела
     * @return string Excerpt
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
     * Извлечь boost (коэффициент релевантности) из data_json.
     *
     * @param array<string, mixed> $data Данные из data_json
     * @return float|null Boost или null, если не указан
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
     * Маппить термы Entry в структуру для индекса.
     *
     * Преобразует коллекцию термов в массив с taxonomy и slug.
     *
     * @param \App\Models\Entry $entry Запись с загруженными термами
     * @return list<array{taxonomy: string, slug: string}> Список термов
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

    /**
     * Нормализовать пробелы в тексте.
     *
     * Заменяет множественные пробелы на один и удаляет пробелы в начале/конце.
     *
     * @param string $value Исходный текст
     * @return string Нормализованный текст
     */
    private function normalizeWhitespace(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }
}


