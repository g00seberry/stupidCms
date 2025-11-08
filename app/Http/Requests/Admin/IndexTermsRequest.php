<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:10|max:100',
            'sort' => [
                'sometimes',
                'string',
                Rule::in([
                    'created_at.desc',
                    'created_at.asc',
                    'name.asc',
                    'name.desc',
                    'slug.asc',
                    'slug.desc',
                ]),
            ],
        ];
    }
}


