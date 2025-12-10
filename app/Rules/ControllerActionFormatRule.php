<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\DynamicRoutes\DynamicRouteGuard;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации: формат action для action_type=controller.
 *
 * Проверяет, что action соответствует одному из допустимых форматов:
 * - Controller@method: 'App\Http\Controllers\BlogController@show'
 * - Invokable controller: 'App\Http\Controllers\HomeController'
 * - View: 'view:pages.about'
 * - Redirect: 'redirect:/new-page' или 'redirect:/new-page:301'
 *
 * Также проверяет, что контроллер разрешён через DynamicRouteGuard.
 *
 * @package App\Rules
 */
final class ControllerActionFormatRule implements ValidationRule, DataAwareRule
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
     * Проверяет формат action и разрешённость контроллера.
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

        // Проверяем, что action_type=controller
        $actionType = $this->data['action_type'] ?? null;
        if ($actionType !== 'controller') {
            return; // Правило применяется только для controller
        }

        $guard = new DynamicRouteGuard();

        // Проверяем формат view:
        if (str_starts_with($value, 'view:')) {
            return; // Формат view: корректен
        }

        // Проверяем формат redirect:
        if (str_starts_with($value, 'redirect:')) {
            return; // Формат redirect: корректен
        }

        // Проверяем формат Controller@method или Invokable controller
        // Извлекаем имя контроллера (до @ или всё значение)
        $controller = str_contains($value, '@') ? explode('@', $value)[0] : $value;

        // Проверяем, что контроллер разрешён
        if (! $guard->isControllerAllowed($controller)) {
            $fail("Поле :attribute содержит неразрешённый контроллер '{$controller}'.");
        }
    }
}

