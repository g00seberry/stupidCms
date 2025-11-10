<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $payload = $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail('The media update payload failed validation constraints.')
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}
