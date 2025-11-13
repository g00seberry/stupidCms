<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Carbon;

/**
 * Правило валидации: дата публикации не должна быть в будущем.
 *
 * Проверяет, что published_at не находится в будущем относительно текущего времени.
 * Применяется только для записей со статусом 'published'.
 *
 * @package App\Rules
 */
class PublishedDateNotInFuture implements Rule, DataAwareRule
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
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Определить, прошла ли валидация.
     *
     * Проверяет, что published_at не находится в будущем (для статуса 'published').
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $status = $this->data['status'] ?? 'draft';
        if ($status !== 'published' || empty($value)) {
            return true;
        }

        return Carbon::parse($value, 'UTC')->lte(Carbon::now('UTC'));
    }

    /**
     * Получить сообщение об ошибке валидации.
     *
     * @return string
     */
    public function message(): string
    {
        return __('validation.published_at_not_in_future', [], 'ru');
    }
}

