<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FallbackRequest;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Контроллер для обработки 404 ошибок (fallback маршрут).
 *
 * Обрабатывает все запросы, которые не совпали с другими маршрутами.
 * Логирует информацию о запросе и возвращает соответствующий ответ
 * (JSON для API, HTML для веб-запросов).
 *
 * @package App\Http\Controllers
 */
final class FallbackController extends Controller
{
    /**
     * Обработать fallback запрос (404).
     *
     * Логирует информацию о запросе и возвращает ответ в зависимости
     * от типа запроса (JSON для API, HTML для веб).
     *
     * @param \App\Http\Requests\FallbackRequest $request Запрос
     * @return \Illuminate\Http\Response|\Illuminate\View\View|\Illuminate\Http\JsonResponse Ответ
     */
    public function __invoke(FallbackRequest $request): Response|View|JsonResponse
    {
        Log::info('404 Not Found', [
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->header('referer'),
            'accept' => $request->header('accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            /** @var ErrorFactory $factory */
            $factory = app(ErrorFactory::class);

            $payload = $factory->for(ErrorCode::NOT_FOUND)
                ->detail('The requested resource was not found.')
                ->meta(['path' => $request->path()])
                ->build();

            return ErrorResponseFactory::make($payload);
        }

        return response()->view('errors.404', [
            'path' => $request->path(),
        ], Response::HTTP_NOT_FOUND);
    }
}