<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Fallback контроллер для обработки всех несовпавших маршрутов (404).
 * 
 * Должен быть зарегистрирован строго последним в RouteServiceProvider,
 * чтобы обрабатывать только те запросы, которые не совпали с предыдущими роутами.
 */
class FallbackController extends Controller
{
    /**
     * Обрабатывает все несовпавшие запросы.
     * 
     * @param Request $request
     * @return JsonResponse|Response|View
     */
    public function __invoke(Request $request): JsonResponse|Response|View
    {
        // Лёгкая телеметрия по 404 для поиска битых ссылок
        // Логируем структурированные данные: path, referer, accept, method
        Log::info('404 Not Found', [
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->header('referer'),
            'accept' => $request->header('accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        // Детекция JSON запросов:
        // 1. Явный запрос JSON через expectsJson() (X-Requested-With: XMLHttpRequest или Accept: application/json)
        // 2. Запросы к API путям (is('api/*'))
        // 3. Явное указание wantsJson() для клиентов без заголовков
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            // RFC 7807: problem+json для API ошибок
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'The requested resource was not found.',
                'path' => $request->path(),
            ], 404)->header('Content-Type', 'application/problem+json');
        }

        // Для веб-запросов возвращаем view с ошибкой 404
        return response()->view('errors.404', [
            'path' => $request->path(),
        ], 404);
    }
}

