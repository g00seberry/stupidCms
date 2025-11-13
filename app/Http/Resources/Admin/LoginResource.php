<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\JwtCookies;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на успешный вход в систему.
 *
 * Возвращает данные пользователя и устанавливает JWT cookies
 * (access и refresh токены) в HttpOnly cookies.
 *
 * @package App\Http\Resources\Admin
 */
class LoginResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param \App\Models\User $user Пользователь
     * @param string $accessToken JWT access токен
     * @param string $refreshToken JWT refresh токен
     */
    public function __construct(
        private readonly User $user,
        private readonly string $accessToken,
        private readonly string $refreshToken
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, array<string, mixed>> Массив с данными пользователя
     */
    public function toArray($request): array
    {
        return [
            'user' => [
                'id' => (int) $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->name,
            ],
        ];
    }

    /**
     * Настроить HTTP ответ для Login.
     *
     * Устанавливает JWT cookies (access и refresh) в HttpOnly cookies.
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


