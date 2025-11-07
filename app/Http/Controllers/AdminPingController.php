<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Тестовый контроллер для проверки порядка роутинга.
 * 
 * Маршрут /admin/ping должен обрабатываться до fallback,
 * что подтверждает правильный порядок загрузки роутов.
 */
class AdminPingController extends Controller
{
    /**
     * GET /admin/ping
     * 
     * Простой эндпоинт для проверки, что системные маршруты
     * обрабатываются раньше fallback.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'message' => 'Admin ping route is working',
            'route' => '/admin/ping',
        ]);
    }
}

