<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Options;

use App\Models\Option;
use App\Rules\JsonValue;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для создания/обновления опции (PUT).
 *
 * Валидирует данные для создания или обновления опции:
 * - namespace: обязательный namespace (regex, из route параметра)
 * - key: обязательный ключ (regex, из route параметра)
 * - value: обязательное значение (может быть null, JSON, максимум 65536 байт)
 * - description: опциональное описание (максимум 255 символов)
 *
 * @package App\Http\Requests\Admin\Options
 */
class PutOptionRequest extends FormRequest
{
    /**
     * Паттерн для валидации namespace/key опций.
     *
     * @var string
     */
    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    /**
     * @var \App\Models\Option|null Кэшированная модель опции
     */
    private ?Option $resolvedOption = null;

    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует права write для Option (создаёт новую опцию, если не существует).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $option = $this->option();

        return $user->can('write', $option);
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - namespace: обязательный namespace (regex, из route параметра)
     * - key: обязательный ключ (regex, из route параметра)
     * - value: обязательное значение (может быть null, JSON, максимум 65536 байт)
     * - description: опциональное описание (максимум 255 символов)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'key' => ['required', 'string', 'regex:' . self::KEY_PATTERN],
            'value' => ['required', 'nullable', new JsonValue(maxBytes: 65536)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Подготовить данные для валидации.
     *
     * Извлекает namespace и key из route параметров.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'namespace' => $this->route('namespace'),
            'key' => $this->route('key'),
        ]);
    }

    /**
     * Обработать ошибки валидации.
     *
     * Выбрасывает HttpErrorException с кодом INVALID_OPTION_PAYLOAD,
     * INVALID_JSON_VALUE или INVALID_OPTION_IDENTIFIER в зависимости от типа ошибки.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     * @throws \App\Support\Errors\HttpErrorException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        $errors = $validator->errors();

        $code = 'INVALID_OPTION_PAYLOAD';
        if ($errors->has('value')) {
            $code = 'INVALID_JSON_VALUE';
        } elseif ($errors->has('namespace') || $errors->has('key')) {
            $code = 'INVALID_OPTION_IDENTIFIER';
        }

        $enum = ErrorCode::tryFrom($code) ?? ErrorCode::INVALID_OPTION_PAYLOAD;

        /** @var ErrorFactory $factory */
        $factory = app(ErrorFactory::class);

        $detail = match ($enum) {
            ErrorCode::INVALID_OPTION_IDENTIFIER => 'The provided option namespace/key is invalid.',
            ErrorCode::INVALID_JSON_VALUE => 'The provided JSON value is invalid.',
            default => 'Invalid option payload.',
        };

        $payload = $factory->for($enum)
            ->detail($detail)
            ->meta(['errors' => $errors->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }

    /**
     * Получить опцию из route параметров или создать новую.
     *
     * Если опция не существует, создаёт новую модель Option
     * с указанными namespace и key.
     *
     * @return \App\Models\Option Модель опции
     */
    public function option(): Option
    {
        if ($this->resolvedOption !== null) {
            return $this->resolvedOption;
        }

        $namespace = (string) $this->route('namespace');
        $key = (string) $this->route('key');

        $option = Option::withTrashed()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if ($option === null) {
            $option = new Option([
                'namespace' => $namespace,
                'key' => $key,
            ]);
        }

        return $this->resolvedOption = $option;
    }
}

