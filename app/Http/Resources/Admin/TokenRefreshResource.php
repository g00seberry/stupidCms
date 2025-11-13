<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Support\JwtCookies;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на успешную ротацию refresh токена.
 *
 * Возвращает сообщение об успехе и устанавливает новые JWT cookies
 * (access и refresh токены) в HttpOnly cookies.
 *
 * @package App\Http\Resources\Admin
 */
final class TokenRefreshResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param string $accessToken Новый JWT access токен
     * @param string $refreshToken Новый JWT refresh токен
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $refreshToken
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, string> Массив с сообщением об успехе
     */
    public function toArray($request): array
    {
        return [
            'message' => 'Tokens refreshed successfully.',
        ];
    }

    /**
     * Настроить HTTP ответ для TokenRefresh.
     *
     * Устанавливает новые JWT cookies (access и refresh) в HttpOnly cookies.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->headers->setCookie(JwtCookies::access($this->accessToken));
        $response->headers->setCookie(JwtCookies::refresh($this->refreshToken));

        parent::prepareAdminResponse($request, $response);
    }
}
