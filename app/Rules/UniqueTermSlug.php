<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Term;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: уникальность slug терма в рамках таксономии.
 *
 * Проверяет, что slug не занят другим термом той же таксономии.
 * Учитывает только неудалённые термы. Поддерживает исключение терма по ID.
 *
 * @package App\Rules
 */
class UniqueTermSlug implements ValidationRule
{
    /**
     * @param int|null $taxonomyId ID таксономии для проверки уникальности
     * @param int|null $exceptTermId ID терма, который исключить из проверки (для update)
     */
    public function __construct(
        private readonly ?int $taxonomyId,
        private readonly ?int $exceptTermId = null
    ) {
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет уникальность slug в рамках таксономии.
     * Если slug занят (только неудалённые термы), добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->taxonomyId || ! is_string($value) || $value === '') {
            return;
        }

        $query = Term::query()
            ->where('taxonomy_id', $this->taxonomyId)
            ->where('slug', $value)
            ->whereNull('deleted_at');

        if ($this->exceptTermId) {
            $query->where('id', '!=', $this->exceptTermId);
        }

        if ($query->exists()) {
            $fail('The slug is already taken for this taxonomy.');
        }
    }
}


