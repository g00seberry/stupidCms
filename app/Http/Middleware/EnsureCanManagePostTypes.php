<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Middleware для проверки прав управления типами записей.
 *
 * Обеспечивает, что аутентифицированный пользователь имеет право 'manage.posttypes'.
 *
 * Выбрасывает AuthorizationException, которое обрабатывается глобальным
 * обработчиком исключений (bootstrap/app.php) и рендерится как RFC7807 problem+json.
 *
 * @package App\Http\Middleware
 */
final class EnsureCanManagePostTypes
{
    /**
     * Обработать входящий запрос.
     *
     * Проверяет право 'manage.posttypes' через Gate.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(Request $request, Closure $next)
    {
        Gate::authorize('manage.posttypes');
        
        return $next($request);
    }
}

