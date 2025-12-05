<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\View\TemplatePathValidator;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации для пути шаблона.
 *
 * Проверяет, что путь шаблона находится в папке templates или дочерних папках.
 * Все остальные директории считаются системными и недоступны для шаблонов.
 *
 * @package App\Rules
 */
final class TemplatePathRule implements ValidationRule
{
    /**
     * @param \App\Domain\View\TemplatePathValidator $validator Валидатор путей шаблонов
     */
    public function __construct(
        private readonly TemplatePathValidator $validator
    ) {
    }

    /**
     * Выполнить правило валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для сообщения об ошибке
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        if ($value === '') {
            return; // Пустая строка допустима (nullable)
        }

        $normalized = $this->validator->normalize($value);

        if (!$this->validator->validate($normalized)) {
            $fail('The :attribute must be a path within the templates directory (e.g., templates.article or templates.custom.page).');
        }
    }
}

