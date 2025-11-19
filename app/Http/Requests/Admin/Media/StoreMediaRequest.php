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
 * Request для загрузки медиа-файла.
 *
 * Валидирует данные для загрузки медиа-файла:
 * - file: обязательный файл (размер и MIME тип из конфига)
 * - title: опциональный заголовок (минимум 1, максимум 255 символов)
 * - alt: опциональный alt текст (минимум 1, максимум 255 символов)
 *
 * @package App\Http\Requests\Admin\Media
 */
class StoreMediaRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права create для Media.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
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
     * Валидирует:
     * - file: обязательный файл (размер и MIME тип из конфига media)
     * - title: опциональный заголовок (минимум 1, максимум 255 символов, если указан)
     * - alt: опциональный alt текст (минимум 1, максимум 255 символов, если указан)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxUploadMb = (int) config('media.max_upload_mb', 25);
        $maxUploadKb = $maxUploadMb * 1024;
        $allowedMimes = implode(',', config('media.allowed_mimes', []));

        return [
            'file' => "required|file|max:{$maxUploadKb}|mimetypes:{$allowedMimes}",
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
            ->detail('The media payload failed validation constraints.')
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}
