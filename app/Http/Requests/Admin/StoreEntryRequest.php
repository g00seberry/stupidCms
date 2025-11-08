<?php

namespace App\Http\Requests\Admin;

use App\Rules\Publishable;
use App\Rules\ReservedSlug;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postTypeSlug = $this->input('post_type', 'page');

        return [
            'post_type' => 'required|string|exists:post_types,slug',
            'title' => 'required|string|max:500',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                new UniqueEntrySlug($postTypeSlug),
                new ReservedSlug(),
                (new Publishable())->setData($this->all()),
            ],
            'content_json' => 'nullable|array',
            'meta_json' => 'nullable|array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'template_override' => 'nullable|string|max:255',
            'term_ids' => 'nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_published') && ! $this->has('published_at')) {
            $this->merge([
                'published_at' => now()->toDateTimeString(),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_published')) {
                return;
            }

            if ($this->has('slug') && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'A valid slug is required when publishing an entry.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'post_type.required' => 'The post type field is required.',
            'post_type.exists' => 'The specified post type does not exist.',
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'slug.regex' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }
}

