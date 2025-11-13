<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Media;

use App\Models\Media;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для обновления медиа-файла.
 *
 * Валидирует данные для обновления метаданных медиа-файла:
 * - Все поля опциональны
 * - title: опциональный заголовок (максимум 255 символов)
 * - alt: опциональный alt текст (максимум 255 символов)
 * - collection: опциональная коллекция (regex, максимум 64 символа)
 *
 * @package App\Http\Requests\Admin\Media
 */
class UpdateMediaRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права update для конкретного Media.
     *
     * @return bool
     */
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
     * Получить правила валидации для запроса.
     *
     * Валидирует (все поля опциональны):
     * - title: заголовок (максимум 255 символов)
     * - alt: alt текст (максимум 255 символов)
     * - collection: коллекция (regex, максимум 64 символа)
     *
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

    /**
     * Обработать ошибки валидации.
     *
     * Выбрасывает HttpErrorException с кодом VALIDATION_ERROR.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     * @throws \App\Support\Errors\HttpErrorException
     */
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
