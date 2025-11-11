<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('post_types', 'slug'),
                new ReservedSlug(),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'template' => [
                'nullable',
                'string',
                'max:255',
            ],
            'options_json' => [
                'sometimes',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value === []) {
                        return;
                    }

                    if (array_is_list($value)) {
                        $fail('The options_json field must be an object.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'The slug field is required.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, underscores, and hyphens.',
            'name.required' => 'The name field is required.',
            'options_json.array' => 'The options_json field must be an object.',
        ];
    }
}


