<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Entry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: глобальная уникальность slug записи.
 *
 * Проверяет, что slug не занят другой записью (глобальная уникальность).
 * Учитывает мягко удалённые записи. Поддерживает исключение записи по ID.
 *
 * @package App\Rules
 */
class UniqueEntrySlug implements ValidationRule
{
    /**
     * @param int|null $exceptEntryId ID записи, которую исключить из проверки (для update)
     */
    public function __construct(
        private ?int $exceptEntryId = null
    ) {
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет глобальную уникальность slug (все записи, кроме исключенной).
     * Если slug занят (включая мягко удалённые записи), добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Check if slug is already taken globally (including soft-deleted)
        $query = Entry::query()
            ->withTrashed()
            ->where('slug', $value);

        if ($this->exceptEntryId) {
            $query->where('id', '!=', $this->exceptEntryId);
        }

        if ($query->exists()) {
            $fail('The slug is already taken.');
        }
    }
}

