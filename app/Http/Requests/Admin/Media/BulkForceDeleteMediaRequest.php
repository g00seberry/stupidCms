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
 * Request для массового окончательного удаления медиа-файлов.
 *
 * Валидирует массив идентификаторов медиа-файлов для массового окончательного удаления.
 *
 * @package App\Http\Requests\Admin\Media
 */
class BulkForceDeleteMediaRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права forceDelete для Media. Для массовых операций проверяем
     * наличие права через hasAdminPermission, так как Policy::forceDelete
     * требует конкретный экземпляр Media.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->hasAdminPermission('media.forceDelete');
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - ids: обязательный массив идентификаторов медиа-файлов (минимум 1 элемент, максимум 100)
     * - ids.*: каждый идентификатор должен быть строкой (ULID)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * Получить кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'The ids field is required.',
            'ids.array' => 'The ids must be an array.',
            'ids.min' => 'At least one media ID is required.',
            'ids.max' => 'Maximum 100 media IDs allowed per request.',
            'ids.*.required' => 'Each media ID is required.',
            'ids.*.string' => 'Each media ID must be a string.',
            'ids.*.size' => 'Each media ID must be exactly 26 characters (ULID).',
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
            ->detail('The media bulk force delete payload failed validation constraints.')
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }

    /**
     * Выполнить кастомную валидацию.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('ids', []);
            if (! is_array($ids)) {
                return;
            }

            // Проверяем наличие дубликатов
            $uniqueIds = array_unique($ids);
            if (count($uniqueIds) !== count($ids)) {
                $validator->errors()->add(
                    'ids',
                    'Duplicate media IDs are not allowed.'
                );
            }
        });
    }
}

