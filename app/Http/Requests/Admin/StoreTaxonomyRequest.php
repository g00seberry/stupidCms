<?php

namespace App\Http\Requests\Admin;

use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxonomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('taxonomies', 'slug'),
                new ReservedSlug(),
            ],
            'hierarchical' => 'sometimes|boolean',
            'options_json' => 'sometimes|array',
        ];
    }
}


