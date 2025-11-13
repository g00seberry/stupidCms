<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для получения списка опций в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска и пагинации
 * для списка опций в указанном namespace.
 *
 * @package App\Http\Requests\Admin\Options
 */
class IndexOptionsRequest extends FormRequest
{
    /**
     * Паттерн для валидации namespace/key опций.
     *
     * @var string
     */
    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права viewAny для Option.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('viewAny', Option::class) : false;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - namespace: обязательный namespace (regex, из route параметра)
     * - q: опциональный поисковый запрос (максимум 255 символов)
     * - deleted: опциональный фильтр удалённых (with, only)
     * - page/per_page: опциональные параметры пагинации
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'q' => ['nullable', 'string', 'max:255'],
            'deleted' => ['nullable', 'in:with,only'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Подготовить данные для валидации.
     *
     * Извлекает namespace из route параметра.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'namespace' => $this->route('namespace'),
        ]);
    }

    /**
     * Обработать ошибки валидации.
     *
     * Выбрасывает HttpErrorException с кодом INVALID_OPTION_IDENTIFIER
     * или INVALID_OPTION_FILTERS в зависимости от типа ошибки.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     * @throws \App\Support\Errors\HttpErrorException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $code = $errors->has('namespace') ? ErrorCode::INVALID_OPTION_IDENTIFIER : ErrorCode::INVALID_OPTION_FILTERS;

        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $detail = $code === ErrorCode::INVALID_OPTION_IDENTIFIER
            ? 'The provided option namespace/key is invalid.'
            : 'Invalid option filter parameters.';

        $payload = $factory->for($code)
            ->detail($detail)
            ->meta(['errors' => $errors->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }
}

