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
 */
class RejectReservedIfMatched
{
    public function __construct(
        private PathReservationService $pathReservationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        
        if ($slug && $this->pathReservationService->isReserved("/{$slug}")) {
            abort(404);
        }

        return $next($request);
    }
}

