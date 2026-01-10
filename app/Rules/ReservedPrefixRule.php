<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: URI/префикс не должен быть в списке зарезервированных.
 *
 * Проверяет, что URI или префикс не начинается с зарезервированных префиксов
 * из конфигурации dynamic-routes через DynamicRouteValidator.
 *
 * @package App\Rules
 */
final class ReservedPrefixRule implements ValidationRule
{
    /**
     * Выполнить правило валидации.
     *
     * Проверяет, что значение не начинается с зарезервированных префиксов.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): \Illuminate\Translation\PotentiallyTranslatedString $fail Callback для ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            return; // Пропускаем пустые значения (валидируется отдельно через nullable/required)
        }

        $guard = new DynamicRouteValidator();

        // Убираем начальный и конечный слэш для нормализации
        $normalized = trim($value, '/');

        if ($guard->isPrefixReserved($normalized)) {
            $fail("Поле :attribute не может использовать зарезервированный префикс '{$normalized}'.");
        }
    }
}

