<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\Admin\UserResource;
use Illuminate\Support\Facades\Auth;

/**
 * Контроллер для получения данных текущего авторизованного пользователя.
 *
 * Возвращает информацию о пользователе из JWT токена и устанавливает CSRF cookie
 * для последующих state-changing запросов.
 *
 * @package App\Http\Controllers\Auth
 */
final class CurrentUserController
{
    /**
     * Получить данные текущего авторизованного пользователя.
     *
     * Также выдает CSRF токен в cookie для последующих state-changing запросов.
     *
     * @group Auth
     * @subgroup Sessions
     * @name Current User
     * @authenticated
     * @responseHeader Set-Cookie "cms_csrf=...; Path=/; Secure"
     * @response status=200 {
     *   "id": 1,
     *   "email": "admin@stupidcms.dev",
     *   "name": "Admin"
     * }
     * @response status=401 {
     *   "type": "about:blank",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication required."
     * }
     */
    public function show(): UserResource
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return new UserResource($user);
    }
}

