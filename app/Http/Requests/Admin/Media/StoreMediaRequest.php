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
 * - title: опциональный заголовок (максимум 255 символов)
 * - alt: опциональный alt текст (максимум 255 символов)
 * - collection: опциональная коллекция (regex, максимум 64 символа)
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
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - file: обязательный файл (размер и MIME тип из конфига media)
     * - title: опциональный заголовок (максимум 255 символов)
     * - alt: опциональный alt текст (максимум 255 символов)
     * - collection: опциональная коллекция (regex, максимум 64 символа)
     *
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
