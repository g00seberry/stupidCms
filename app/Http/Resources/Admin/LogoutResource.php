<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на выход из системы.
 *
 * Возвращает пустой ответ со статусом 204 и устанавливает пустые cookies
 * для удаления JWT токенов из браузера.
 *
 * @package App\Http\Resources\Admin
 */
final class LogoutResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param array<int, \Symfony\Component\HttpFoundation\Cookie> $cookies Cookies для удаления (Max-Age=0)
     */
    public function __construct(
        private readonly array $cookies
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Пустой массив (тело ответа будет null)
     */
    public function toArray($request): array
    {
        return [];
    }

    /**
     * Настроить HTTP ответ для Logout.
     *
     * Устанавливает пустые cookies для удаления JWT токенов
     * и статус 204 (No Content).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        $response->setContent(null);

        parent::prepareAdminResponse($request, $response);
    }
}


