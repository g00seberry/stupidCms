<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\Media;
use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxUpload = (int) config('media.max_upload_mb', 25) * 1024;
        $allowedMimes = implode(',', config('media.allowed_mimes', []));

        return [
            'file' => "required|file|max:{$maxUpload}|mimetypes:{$allowedMimes}",
            'title' => 'nullable|string|max:255',
            'alt' => 'nullable|string|max:255',
            'collection' => 'nullable|string|max:64|regex:/^[a-z0-9-_.]+$/i',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = ProblemResponseFactory::make(
            ProblemType::VALIDATION_ERROR,
            detail: 'The media payload failed validation constraints.',
            extensions: ['errors' => $validator->errors()->messages()]
        );

        throw new HttpResponseException($response);
    }
}
