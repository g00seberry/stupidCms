<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReservedRoute;
use App\Models\User;

/**
 * Политика авторизации для RouteReservation.
 *
 * Все методы возвращают false (резервации путей управляются через
 * проверку is_admin в контроллерах). Политика оставлена для совместимости.
 *
 * @package App\Policies
 */
class RouteReservationPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые резервации путей.
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь просматривать резервацию пути.
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\ReservedRoute $reservedRoute Резервация пути
     * @return bool
     */
    public function view(User $user, ReservedRoute $reservedRoute): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь создавать резервации путей.
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь обновлять резервацию пути.
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\ReservedRoute $reservedRoute Резервация пути
     * @return bool
     */
    public function update(User $user, ReservedRoute $reservedRoute): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь удалять резервацию пути.
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\ReservedRoute $reservedRoute Резервация пути
     * @return bool
     */
    public function delete(User $user, ReservedRoute $reservedRoute): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь удалять любые резервации путей (для коллекционных операций).
     *
     * Всегда возвращает false (управляется через is_admin в контроллерах).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}

