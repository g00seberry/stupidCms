<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Тестовый контроллер для проверки регистрации динамических маршрутов.
 *
 * Используется только в тестах.
 */
class TestController extends Controller
{
    /**
     * Обработать запрос.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(): JsonResponse
    {
        return response()->json(['message' => 'Test controller works']);
    }

    /**
     * Invokable метод.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['message' => 'Invokable controller works']);
    }
}

