<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ReservedRoute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: проверка конфликтов с зарезервированными путями.
 *
 * Проверяет, что slug не конфликтует с зарезервированными путями
 * (kind='path' или kind='prefix').
 *
 * @package App\Rules
 */
class ReservedSlug implements ValidationRule
{
    /**
     * Выполнить правило валидации.
     *
     * Проверяет конфликты с зарезервированными путями:
     * - Для kind='path': точное совпадение
     * - Для kind='prefix': совпадение или начало с префикса
     *
     * Если конфликт обнаружен, добавляет ошибку валидации.
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

        $slug = strtolower(trim($value));
        $slugNormalized = '/' . ltrim($slug, '/');

        $conflicts = ReservedRoute::query()
            ->get(['path', 'kind'])
            ->contains(function (ReservedRoute $route) use ($slug, $slugNormalized) {
                $path = strtolower($route->path ?? '');
                $pathNormalized = '/' . ltrim($path, '/');
                $pathTrimmed = ltrim($path, '/');

                if ($route->kind === 'path') {
                    return in_array($slug, [$path, $pathTrimmed, $pathNormalized], true)
                        || in_array($slugNormalized, [$path, $pathTrimmed, $pathNormalized], true);
                }

                    // 'prefix' kind
                $slugTrimmed = ltrim($slug, '/');
                $slugNormalizedTrimmed = ltrim($slugNormalized, '/');

                return in_array($path, [$slug, $slugNormalized], true)
                    || in_array($pathTrimmed, [$slugTrimmed, $slugNormalizedTrimmed], true)
                    || str_starts_with($slugTrimmed, rtrim($pathTrimmed, '/') . '/')
                    || str_starts_with($slugNormalizedTrimmed, rtrim($pathTrimmed, '/') . '/');
            });

        if ($conflicts) {
            $fail('The slug conflicts with a reserved route.');
        }
    }
}

