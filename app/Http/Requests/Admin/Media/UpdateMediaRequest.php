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
 * - title: опциональный заголовок (минимум 1, максимум 255 символов, если указан)
 * - alt: опциональный alt текст (минимум 1, максимум 255 символов, если указан)
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
     * Подготовить данные для валидации.
     *
     * Нормализует пустые строки в null для title и alt, чтобы они не проходили валидацию min:1.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Нормализация title: пустые строки → null
        if ($this->has('title') && is_string($this->input('title'))) {
            $title = trim($this->input('title'));
            $this->merge(['title' => $title !== '' ? $title : null]);
        }

        // Нормализация alt: пустые строки → null
        if ($this->has('alt') && is_string($this->input('alt'))) {
            $alt = trim($this->input('alt'));
            $this->merge(['alt' => $alt !== '' ? $alt : null]);
        }
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует (все поля опциональны):
     * - title: заголовок (минимум 1, максимум 255 символов, если указан)
     * - alt: alt текст (минимум 1, максимум 255 символов, если указан)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|filled|string|min:1|max:255',
            'alt' => 'nullable|filled|string|min:1|max:255',
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
