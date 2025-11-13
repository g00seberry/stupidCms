<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Entry;
use App\Models\PostType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: уникальность slug записи в рамках типа записи.
 *
 * Проверяет, что slug не занят другой записью того же типа.
 * Учитывает мягко удалённые записи. Поддерживает исключение записи по ID.
 *
 * @package App\Rules
 */
class UniqueEntrySlug implements ValidationRule
{
    /**
     * @param string $postTypeSlug Slug типа записи для проверки уникальности
     * @param int|null $exceptEntryId ID записи, которую исключить из проверки (для update)
     */
    public function __construct(
        private string $postTypeSlug,
        private ?int $exceptEntryId = null
    ) {
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет существование PostType и уникальность slug в его рамках.
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

        // Find post_type_id by slug
        $postType = PostType::query()->where('slug', $this->postTypeSlug)->first();
        
        if (! $postType) {
            $fail('The specified post type does not exist.');
            return;
        }

        // Check if slug is already taken in this post_type (including soft-deleted)
        $query = Entry::query()
            ->withTrashed()
            ->where('post_type_id', $postType->id)
            ->where('slug', $value);

        if ($this->exceptEntryId) {
            $query->where('id', '!=', $this->exceptEntryId);
        }

        if ($query->exists()) {
            $fail('The slug is already taken for this post type.');
        }
    }
}

