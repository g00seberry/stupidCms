<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\JwtCookies;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для User в админ-панели.
 *
 * Форматирует данные пользователя и устанавливает CSRF cookie
 * для последующих state-changing запросов.
 *
 * @package App\Http\Resources\Admin
 */
class UserResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param \App\Models\User $resource Модель пользователя
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями пользователя (id, email, name, is_admin, created_at, updated_at)
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        return [
            'id' => (int) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'is_admin' => (bool) $user->is_admin,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Настроить HTTP ответ для User.
     *
     * Устанавливает CSRF cookie для аутентифицированных пользователей.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        // Issue CSRF token for authenticated users
        $csrfToken = Str::random(40);
        $response->headers->setCookie(JwtCookies::csrf($csrfToken));

        parent::prepareAdminResponse($request, $response);
    }
}

