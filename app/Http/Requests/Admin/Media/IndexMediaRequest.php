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
     * Подготовить данные для валидации.
     *
     * Преобразует плоские параметры запроса в вложенную структуру для совместимости с тестами.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Преобразуем плоские параметры в filters
        if ($this->has('q') || $this->has('kind') || $this->has('mime') || $this->has('deleted') || $this->has('sort') || $this->has('order')) {
            $data['filters'] = [];
            
            if ($this->has('q')) {
                $data['filters']['q'] = $this->input('q');
            }
            if ($this->has('kind')) {
                $data['filters']['kind'] = $this->input('kind');
            }
            if ($this->has('mime')) {
                $data['filters']['mime'] = $this->input('mime');
            }
            if ($this->has('deleted')) {
                $data['filters']['deleted'] = $this->input('deleted');
            }
            if ($this->has('sort')) {
                $data['filters']['sort'] = $this->input('sort');
            }
            if ($this->has('order')) {
                $data['filters']['order'] = $this->input('order');
            }
        }

        // Преобразуем плоские параметры в pagination
        if ($this->has('per_page') || $this->has('page')) {
            $data['pagination'] = [];
            
            if ($this->has('per_page')) {
                $data['pagination']['per_page'] = $this->input('per_page');
            }
            if ($this->has('page')) {
                $data['pagination']['page'] = $this->input('page');
            }
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - filters.q: опциональный поисковый запрос (максимум 255 символов)
     * - filters.kind: опциональный тип медиа (image, video, audio, document)
     * - filters.mime: опциональный MIME тип (максимум 120 символов)
     * - filters.deleted: опциональный фильтр удалённых (with, only)
     * - filters.sort: опциональная сортировка (created_at, size_bytes, mime)
     * - filters.order: опциональный порядок (asc, desc)
     * - pagination.page: опциональный номер страницы
     * - pagination.per_page: опциональное количество на странице (1-100)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filters' => 'nullable|array',
            'filters.q' => 'nullable|string|max:255',
            'filters.kind' => 'nullable|string|in:image,video,audio,document',
            'filters.mime' => 'nullable|string|max:120',
            'filters.deleted' => 'nullable|string|in:with,only',
            'filters.sort' => 'nullable|string|in:created_at,size_bytes,mime',
            'filters.order' => 'nullable|string|in:asc,desc',
            'pagination' => 'nullable|array',
            'pagination.page' => 'nullable|integer|min:1',
            'pagination.per_page' => 'nullable|integer|min:1|max:100',
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
