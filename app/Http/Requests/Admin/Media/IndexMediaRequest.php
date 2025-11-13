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
 * Request для получения списка медиа-файлов в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка медиа-файлов.
 *
 * @package App\Http\Requests\Admin\Media
 */
class IndexMediaRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права viewAny для Media.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Media::class) ?? false;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - q: опциональный поисковый запрос (максимум 255 символов)
     * - kind: опциональный тип медиа (image, video, audio, document)
     * - mime: опциональный MIME тип (максимум 120 символов)
     * - collection: опциональная коллекция (максимум 64 символа)
     * - deleted: опциональный фильтр удалённых (with, only)
     * - sort: опциональная сортировка (created_at, size_bytes, mime)
     * - order: опциональный порядок (asc, desc)
     * - page/per_page: опциональные параметры пагинации
     *
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
            ->detail('Invalid media filter parameters.')
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}
