<?php

namespace App\Http\Middleware;

use App\Domain\Routing\PathReservationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для дополнительной защиты от ложных срабатываний плоской маршрутизации.
 *
 * Проверяет, не зарезервирован ли путь, если он совпал с /{slug} маршрутом.
 * Это защита на случай, если список зарезервированных изменился после route:cache.
 *
 * Использование: опционально, так как основная защита на уровне ReservedPattern.
 *
 * @package App\Http\Middleware
 */
class RejectReservedIfMatched
{
    /**
     * @param \App\Domain\Routing\PathReservationService $pathReservationService Сервис резервации путей
     */
    public function __construct(
        private PathReservationService $pathReservationService
    ) {}

    /**
     * Обработать входящий запрос.
     *
     * Проверяет, не зарезервирован ли slug из маршрута.
     * В testing окружении обрабатывает отсутствие таблицы (после route:cache).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        
        if ($slug) {
            // В production проверяем без try/catch для производительности
            // В testing обрабатываем отсутствие таблицы (после route:cache)
            if (app()->environment('testing')) {
                try {
                    if ($this->pathReservationService->isReserved("/{$slug}")) {
                        abort(404);
                    }
                } catch (\Illuminate\Database\QueryException | \PDOException $e) {
                    // Если таблицы нет, пропускаем проверку
                    // Основная защита на уровне ReservedPattern все равно работает
                    $code = (string) $e->getCode();
                    if (!in_array($code, ['42S02', 'HY000'], true)) {
                        throw $e;
                    }
                    // Для SQLite также проверяем сообщение об ошибке
                    if ($code === 'HY000' && !str_contains($e->getMessage(), 'no such table')) {
                        throw $e;
                    }
                }
            } else {
                // В production таблица должна существовать
                if ($this->pathReservationService->isReserved("/{$slug}")) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }
}

