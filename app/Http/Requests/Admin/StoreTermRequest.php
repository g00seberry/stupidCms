<?php

namespace App\Http\Requests\Admin;

use App\Models\Taxonomy;
use App\Rules\UniqueTermSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    private ?Taxonomy $resolvedTaxonomy = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taxonomy = $this->taxonomy();

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                new UniqueTermSlug($taxonomy?->getKey()),
            ],
            'meta_json' => 'nullable|array',
            'attach_entry_id' => [
                'nullable',
                'integer',
                Rule::exists('entries', 'id'),
            ],
        ];
    }

    public function taxonomy(): ?Taxonomy
    {
        if ($this->resolvedTaxonomy !== null) {
            return $this->resolvedTaxonomy;
        }

        $slug = (string) $this->route('taxonomy');

        $this->resolvedTaxonomy = Taxonomy::query()->where('slug', $slug)->first();

        return $this->resolvedTaxonomy;
    }
}

