<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\Media;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeParam = $this->route('media');
        $media = $routeParam instanceof Media
            ? $routeParam
            : Media::withTrashed()->find($routeParam);

        if (! $media) {
            return false;
        }

        return $this->user()?->can('update', $media) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'alt' => 'nullable|string|max:255',
            'collection' => 'nullable|string|max:64|regex:/^[a-z0-9-_.]+$/i',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $payload = [
            'type' => 'https://stupidcms.dev/problems/validation-error',
            'title' => 'Validation error',
            'status' => 422,
            'detail' => 'The media update payload failed validation constraints.',
            'errors' => $validator->errors()->messages(),
        ];

        $response = response()->json($payload, 422);
        $response->headers->set('Content-Type', 'application/problem+json');

        throw new HttpResponseException($response);
    }
}
