<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Search;

use App\Domain\Search\SearchQuery;
use App\Domain\Search\ValueObjects\SearchTermFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class QuerySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('search.pagination.max_per_page', 100);

        return [
            'q' => ['nullable', 'string', 'min:2', 'max:200'],
            'post_type' => ['nullable', 'array', 'max:10'],
            'post_type.*' => ['string', 'max:64'],
            'term' => ['nullable', 'array', 'max:20'],
            'term.*' => ['string', 'regex:/^[a-z0-9_.-]+:[a-z0-9_.-]+$/i'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('q', $input) && is_string($input['q'])) {
            $trimmed = trim($input['q']);
            $input['q'] = $trimmed === '' ? null : $trimmed;
        }

        if (isset($input['post_type']) && is_string($input['post_type'])) {
            $input['post_type'] = array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', $input['post_type'])
            ), static fn (string $value): bool => $value !== ''));
        }

        if (isset($input['post_type']) && is_array($input['post_type'])) {
            $input['post_type'] = array_values(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                $input['post_type']
            ));
        }

        if (isset($input['term']) && is_string($input['term'])) {
            $input['term'] = [$input['term']];
        }

        if (isset($input['term']) && is_array($input['term'])) {
            $input['term'] = array_values(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                $input['term']
            ));
        }

        foreach (['from', 'to'] as $dateField) {
            if (array_key_exists($dateField, $input) && is_string($input[$dateField])) {
                $input[$dateField] = trim($input[$dateField]) === '' ? null : $input[$dateField];
            }
        }

        $this->replace($input);
    }

    public function toSearchQuery(): SearchQuery
    {
        $validated = $this->validated();

        $page = (int) ($validated['page'] ?? 1);
        $defaultPerPage = (int) config('search.pagination.per_page', 20);
        $perPage = (int) ($validated['per_page'] ?? $defaultPerPage);

        $terms = [];
        foreach ($validated['term'] ?? [] as $raw) {
            try {
                $terms[] = SearchTermFilter::fromString($raw);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'term' => $exception->getMessage(),
                ]);
            }
        }

        return new SearchQuery(
            query: $validated['q'] ?? null,
            postTypes: $validated['post_type'] ?? [],
            terms: $terms,
            from: isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null,
            to: isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : null,
            page: max(1, $page),
            perPage: max(1, $perPage)
        );
    }
}


