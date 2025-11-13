<?php

namespace App\Http\Requests\Admin;

use App\Models\Term;
use App\Models\Taxonomy;
use App\Rules\UniqueTermSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTermRequest extends FormRequest
{
    private ?Term $resolvedTerm = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $term = $this->term();
        $taxonomy = $term?->taxonomy ?: $this->taxonomyFromRoute();

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                new UniqueTermSlug($taxonomy?->getKey(), $term?->getKey()),
            ],
            'meta_json' => 'sometimes|nullable|array',
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('terms', 'id')->where(function ($query) use ($taxonomy, $term) {
                    if ($taxonomy) {
                        $query->where('taxonomy_id', $taxonomy->id);
                    }
                    if ($term) {
                        // Нельзя сделать родителем самого себя
                        $query->where('id', '!=', $term->id);
                    }
                }),
            ],
        ];
    }

    public function term(): ?Term
    {
        if ($this->resolvedTerm !== null) {
            return $this->resolvedTerm;
        }

        $termId = (int) $this->route('term');
        $this->resolvedTerm = Term::query()
            ->with('taxonomy')
            ->find($termId);

        return $this->resolvedTerm;
    }

    private function taxonomyFromRoute(): ?Taxonomy
    {
        $slug = (string) $this->route('taxonomy');
        if ($slug === '') {
            return null;
        }

        return Taxonomy::query()->where('slug', $slug)->first();
    }
}

