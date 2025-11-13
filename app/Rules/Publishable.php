<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: проверка возможности публикации записи.
 *
 * Валидирует, что если is_published=true, то slug присутствует и валиден.
 * Это правило должно применяться к полю 'slug'.
 *
 * @package App\Rules
 */
class Publishable implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed> Данные запроса для валидации
     */
    protected array $data = [];

    /**
     * Установить данные для валидации.
     *
     * @param array<string, mixed> $data Данные запроса
     * @return static
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что при is_published=true slug присутствует и не пустой.
     * Для update проверяет, что slug валиден (даже если не указан в запросе).
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isPublished = $this->data['is_published'] ?? false;

        // If not being published, no additional checks needed
        if (! $isPublished) {
            return;
        }

        if (is_string($value) && trim($value) === '') {
            $fail('A valid slug is required when publishing an entry.');
            return;
        }

        $isUpdate = isset($this->data['_method']) || request()->isMethod('PUT') || request()->isMethod('PATCH');

        if ($isUpdate && (! is_string($value) || trim($value) === '')) {
            $fail('A valid slug is required when publishing an entry.');
        }
    }
}

