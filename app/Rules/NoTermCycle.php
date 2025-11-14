<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Term;
use App\Models\TermTree;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: предотвращение циклических зависимостей в иерархии термов.
 *
 * Проверяет, что установка parent_id не создаст циклическую зависимость.
 * Цикл возникает, если новый родитель является потомком текущего терма.
 *
 * @package App\Rules
 */
class NoTermCycle implements ValidationRule
{
    /**
     * @param \App\Models\Term|null $term Терм, для которого устанавливается родитель (null для создания нового)
     */
    public function __construct(
        private readonly ?Term $term = null
    ) {
    }

    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что указанный parent_id не является потомком текущего терма.
     * Если цикл будет создан, добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации (parent_id)
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если parent_id не указан или терм не указан (создание нового), проверка не нужна
        if ($value === null || $this->term === null) {
            return;
        }

        $parentId = is_numeric($value) ? (int) $value : null;
        if ($parentId === null) {
            return;
        }

        // Проверяем, является ли parent потомком текущего терма
        // Если да, то установка parent_id создаст цикл: term -> ... -> parent -> term
        $wouldCreateCycle = TermTree::where('ancestor_id', $this->term->id)
            ->where('descendant_id', $parentId)
            ->where('depth', '>', 0) // Исключаем само-ссылку (depth = 0)
            ->exists();

        if ($wouldCreateCycle) {
            $fail('Установка данного родителя создаст циклическую зависимость в иерархии термов.');
        }
    }
}

