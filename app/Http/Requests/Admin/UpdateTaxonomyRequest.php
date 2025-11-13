<?php

namespace App\Http\Requests\Admin;

use App\Models\Taxonomy;
use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxonomyRequest extends FormRequest
{
    private ?Taxonomy $resolvedTaxonomy = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taxonomy = $this->taxonomy();
        $taxonomyId = $taxonomy?->getKey();

        return [
            'label' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('taxonomies', 'slug')->ignore($taxonomyId),
                new ReservedSlug(),
            ],
            'hierarchical' => 'sometimes|boolean',
            'options_json' => 'sometimes|array',
        ];
    }

    public function taxonomy(): ?Taxonomy
    {
        if ($this->resolvedTaxonomy !== null) {
            return $this->resolvedTaxonomy;
        }

        $slug = (string) $this->route('slug');
        $this->resolvedTaxonomy = Taxonomy::query()->where('slug', $slug)->first();

        return $this->resolvedTaxonomy;
    }
}

