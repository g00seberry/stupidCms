<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\Admin\UserResource;
use Illuminate\Support\Facades\Auth;

final class CurrentUserController
{
    /**
     * Получить данные текущего авторизованного пользователя.
     *
     * @group Auth
     * @subgroup Sessions
     * @name Current User
     * @authenticated
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

