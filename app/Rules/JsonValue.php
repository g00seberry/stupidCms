<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;

/**
 * Правило валидации: значение должно быть валидным JSON.
 *
 * Проверяет, что значение может быть закодировано в JSON
 * и не превышает максимальный размер (в байтах).
 *
 * @package App\Rules
 */
final class JsonValue implements ValidationRule
{
    /**
     * @param int $maxBytes Максимальный размер JSON в байтах (по умолчанию 65536)
     */
    public function __construct(
        private readonly int $maxBytes = 65536,
    ) {}

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что значение может быть закодировано в JSON
     * и размер закодированного JSON не превышает maxBytes.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fail(__('validation.invalid_json_value'));
            return;
        }

        if ($this->maxBytes > 0 && strlen($encoded) > $this->maxBytes) {
            $fail(__('validation.json_value_too_large', ['max' => $this->maxBytes]));
        }
    }
}

