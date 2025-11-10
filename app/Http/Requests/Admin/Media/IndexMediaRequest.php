<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\Media;
use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Media::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'kind' => 'nullable|string|in:image,video,audio,document',
            'mime' => 'nullable|string|max:120',
            'collection' => 'nullable|string|max:64',
            'deleted' => 'nullable|string|in:with,only',
            'sort' => 'nullable|string|in:created_at,size_bytes,mime',
            'order' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = ProblemResponseFactory::make(
            ProblemType::VALIDATION_ERROR,
            detail: 'Invalid media filter parameters.',
            extensions: ['errors' => $validator->errors()->messages()]
        );

        throw new HttpResponseException($response);
    }
}
