<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Option;
use App\Models\User;

/**
 * Политика авторизации для Option.
 *
 * Определяет права доступа к опциям на основе
 * административных разрешений пользователя (options.*).
 *
 * @package App\Policies
 */
class OptionPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые опции.
     *
     * Требует права 'options.read'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('options.read');
    }

    /**
     * Определить, может ли пользователь просматривать опцию.
     *
     * Требует права 'options.read'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Option $option Опция
     * @return bool
     */
    public function view(User $user, Option $option): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Определить, может ли пользователь создавать/обновлять опцию.
     *
     * Требует права 'options.write'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Option $option Опция
     * @return bool
     */
    public function write(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.write');
    }

    /**
     * Определить, может ли пользователь удалять опцию.
     *
     * Требует права 'options.delete'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Option $option Опция
     * @return bool
     */
    public function delete(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.delete');
    }

    /**
     * Определить, может ли пользователь восстанавливать опцию.
     *
     * Требует права 'options.restore'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Option $option Опция
     * @return bool
     */
    public function restore(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.restore');
    }
}

