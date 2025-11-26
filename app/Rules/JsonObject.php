<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: значение должно быть объектом (ассоциативным массивом), а не индексированным массивом.
 *
 * Проверяет, что значение является массивом и все ключи являются строками
 * (что означает, что это объект в JSON, а не массив).
 *
 * @package App\Rules
 */
final class JsonObject implements ValidationRule
{
    /**
     * Выполнить правило валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для проверки
     * @param \Closure(string, string): void $fail Callback для сообщения об ошибке
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если значение null, пропускаем (nullable должен обрабатываться отдельно)
        if ($value === null) {
            return;
        }

        // Должно быть массивом
        if (!is_array($value)) {
            $fail('The :attribute field must be an object.');
            return;
        }

        // Проверяем, что это ассоциативный массив (объект), а не индексированный массив
        // Если массив пустой, считаем его объектом
        if (empty($value)) {
            return;
        }

        // Проверяем, что все ключи являются строками
        // Если хотя бы один ключ - число, это индексированный массив
        foreach (array_keys($value) as $key) {
            if (is_int($key)) {
                $fail('The :attribute field must be an object, not an array.');
                return;
            }
        }
    }
}

