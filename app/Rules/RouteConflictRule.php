<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\RouteNodeKind;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DeclarativeRouteLoader;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Contracts\Validation\Rule;

/**
 * Правило валидации для проверки конфликтов маршрутов.
 *
 * Проверяет, что маршрут с указанным URI и методами не существует
 * в декларативных маршрутах или в БД.
 *
 * @package App\Rules
 */
class RouteConflictRule implements Rule
{
    /**
     * @param string|null $excludeId ID маршрута для исключения (при обновлении)
     */
    public function __construct(
        private ?int $excludeId = null,
    ) {}

    /**
     * Определить, прошла ли валидация.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение атрибута
     * @return bool true если валидация прошла, false иначе
     */
    public function passes($attribute, $value): bool
    {
        // Проверяем только для маршрутов (не для групп)
        $request = request();
        if ($request->input('kind') !== RouteNodeKind::ROUTE->value) {
            return true;
        }

        $uri = $request->input('uri');
        $methods = $request->input('methods', []);

        if (!$uri || empty($methods)) {
            return true; // URI и methods будут проверены другими правилами
        }

        // Нормализуем methods в массив (может прийти строка до валидации)
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $repository = app(RouteNodeRepository::class);
        $loader = new DeclarativeRouteLoader();
        $guard = new DynamicRouteGuard($repository, $loader);

        $result = $guard->canCreateRoute($uri, $methods, $this->excludeId);

        return $result['allowed'];
    }

    /**
     * Получить сообщение об ошибке валидации.
     *
     * @return string Сообщение об ошибке
     */
    public function message(): string
    {
        $request = request();
        $uri = $request->input('uri');
        $methods = $request->input('methods', []);

        // Нормализуем methods в массив (может прийти строка до валидации)
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $repository = app(RouteNodeRepository::class);
        $loader = new DeclarativeRouteLoader();
        $guard = new DynamicRouteGuard($repository, $loader);

        $result = $guard->canCreateRoute($uri, $methods, $this->excludeId);

        return $result['reason'] ?? 'Маршрут конфликтует с существующим маршрутом.';
    }
}

