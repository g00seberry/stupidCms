<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Политика авторизации для Entry.
 *
 * Определяет права доступа к записям контента на основе
 * административных разрешений пользователя (manage.entries).
 *
 * @package App\Policies
 */
class EntryPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые записи.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь просматривать запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function view(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь создавать записи.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь обновлять запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function update(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь удалять запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function delete(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь восстанавливать запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function restore(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь окончательно удалять запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function forceDelete(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь публиковать/снимать с публикации запись.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function publish(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь привязывать медиа к записи.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function attachMedia(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Определить, может ли пользователь управлять термами записи.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Entry $entry Запись
     * @return bool
     */
    public function manageTerms(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }
}
