<?php

namespace App\Http\Requests;

use App\Rules\PublishedDateNotInFuture;
use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Авторизация должна быть настроена отдельно
    }

    public function rules(): array
    {
        return [
            'post_type_id' => 'required|exists:post_types,id',
            'title' => 'required|string|max:500',
            'slug' => 'nullable|string|max:120',
            'status' => 'required|in:draft,published',
            'published_at' => [
                'nullable',
                'date',
                new PublishedDateNotInFuture(),
            ],
            'author_id' => 'nullable|exists:users,id',
            'data_json' => 'required|array',
            'seo_json' => 'nullable|array',
            'template_override' => 'nullable|string|max:255',
        ];
    }
}

