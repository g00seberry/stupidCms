<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexEntriesRequest extends FormRequest
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
        return [
            'post_type' => 'nullable|string|exists:post_types,slug',
            'status' => 'nullable|string|in:all,draft,published,scheduled,trashed',
            'q' => 'nullable|string|max:500',
            'author_id' => 'nullable|integer|exists:users,id',
            'term' => 'nullable|array',
            'term.*' => 'integer|exists:terms,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'date_field' => 'nullable|string|in:updated,published',
            'sort' => 'nullable|string|in:updated_at.desc,updated_at.asc,published_at.desc,published_at.asc,title.asc,title.desc',
            'per_page' => 'nullable|integer|min:10|max:100',
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
            'post_type.exists' => 'The specified post type does not exist.',
            'status.in' => 'Invalid status value.',
            'author_id.exists' => 'The specified author does not exist.',
            'term.*.exists' => 'One or more specified terms do not exist.',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
            'date_field.in' => 'Invalid date field. Must be "updated" or "published".',
            'sort.in' => 'Invalid sort field.',
            'per_page.min' => 'Results per page must be at least 10.',
            'per_page.max' => 'Results per page must not exceed 100.',
        ];
    }
}

