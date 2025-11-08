<?php

namespace App\Http\Requests\Admin;

use App\Models\Entry;
use App\Rules\Publishable;
use App\Rules\ReservedSlug;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEntryRequest extends FormRequest
{
    private ?Entry $entryModel = null;

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
        $entryId = $this->route('id');
        $entry = Entry::query()->withTrashed()->find($entryId);
        $this->entryModel = $entry;
        
        if (! $entry) {
            return [];
        }

        $postType = $entry->postType;
        $postTypeSlug = $postType ? $postType->slug : 'page';

        return [
            'title' => 'sometimes|required|string|max:500',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                new UniqueEntrySlug($postTypeSlug, $entry->id),
                new ReservedSlug(),
                (new Publishable())->setData($this->all()),
            ],
            'content_json' => 'sometimes|nullable|array',
            'meta_json' => 'sometimes|nullable|array',
            'is_published' => 'sometimes|boolean',
            'published_at' => 'sometimes|nullable|date',
            'template_override' => 'sometimes|nullable|string|max:255',
            'term_ids' => 'sometimes|nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'slug.required' => 'The slug field is required.',
            'slug.regex' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $entry = $this->entryModel;

        if (! $entry) {
            return;
        }

        $validator->after(function (Validator $validator) use ($entry): void {
            if (! $this->boolean('is_published')) {
                return;
            }

            $slugProvided = $this->has('slug');
            $slugValue = $slugProvided ? (string) $this->input('slug') : (string) ($entry->slug ?? '');

            if (trim($slugValue) === '') {
                $validator->errors()->add('slug', 'A valid slug is required when publishing an entry.');
            }
        });
    }
}

