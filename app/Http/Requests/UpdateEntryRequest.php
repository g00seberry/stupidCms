<?php

namespace App\Http\Requests;

use App\Rules\PublishedDateNotInFuture;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Авторизация должна быть настроена отдельно
    }

    public function rules(): array
    {
        return [
            'post_type_id' => 'sometimes|exists:post_types,id',
            'title' => 'sometimes|string|max:500',
            'slug' => 'nullable|string|max:120',
            'status' => 'sometimes|in:draft,published',
            'published_at' => [
                'nullable',
                'date',
                new PublishedDateNotInFuture(),
            ],
            'author_id' => 'nullable|exists:users,id',
            'data_json' => 'sometimes|array',
            'seo_json' => 'nullable|array',
            'template_override' => 'nullable|string|max:255',
        ];
    }
}

