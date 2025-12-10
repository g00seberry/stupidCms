<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Результат валидации контента Entry.
 *
 * Содержит информацию об ошибках валидации, сгруппированных по полям.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class ValidationResult
{
    /**
     * Ошибки валидации, сгруппированные по полям.
     *
     * @var array<string, list<\App\Domain\Blueprint\Validation\ValidationError>>
     */
    private array $errors = [];

    /**
     * Добавить ошибку валидации для поля.
     *
     * @param string $field Путь поля в точечной нотации (например, 'data_json.title')
     * @param \App\Domain\Blueprint\Validation\ValidationError $error Ошибка валидации
     * @return void
     */
    public function addError(string $field, ValidationError $error): void
    {
        if (! isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $error;
    }

    /**
     * Проверить, есть ли ошибки валидации.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Получить все ошибки валидации.
     *
     * @return array<string, list<\App\Domain\Blueprint\Validation\ValidationError>>
     *         Массив, где ключи - пути полей, значения - массивы ошибок
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Получить ошибки для конкретного поля.
     *
     * @param string $field Путь поля
     * @return list<\App\Domain\Blueprint\Validation\ValidationError> Массив ошибок или пустой массив
     */
    public function getErrorsForField(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Получить список всех полей с ошибками.
     *
     * @return list<string>
     */
    public function getFieldsWithErrors(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Проверить, есть ли ошибки для конкретного поля.
     *
     * @param string $field Путь поля
     * @return bool
     */
    public function hasErrorsForField(string $field): bool
    {
        return isset($this->errors[$field]) && ! empty($this->errors[$field]);
    }
}

