<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * Политика авторизации для Media.
 *
 * Определяет права доступа к медиа-файлам на основе
 * административных разрешений пользователя (media.*).
 *
 * @package App\Policies
 */
class MediaPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые медиа-файлы.
     *
     * Требует права 'media.read'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('media.read');
    }

    /**
     * Определить, может ли пользователь просматривать медиа-файл.
     *
     * Требует права 'media.read'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Определить, может ли пользователь создавать медиа-файлы.
     *
     * Требует права 'media.create'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasAdminPermission('media.create');
    }

    /**
     * Определить, может ли пользователь обновлять медиа-файл.
     *
     * Требует права 'media.update'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function update(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }

    /**
     * Определить, может ли пользователь удалять медиа-файл.
     *
     * Требует права 'media.delete'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.delete');
    }

    /**
     * Определить, может ли пользователь восстанавливать медиа-файл.
     *
     * Требует права 'media.restore'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function restore(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.restore');
    }

    /**
     * Определить, может ли пользователь окончательно удалять медиа-файл.
     *
     * Всегда возвращает false (окончательное удаление запрещено).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь загружать медиа-файлы.
     *
     * Требует права 'media.create' (аналогично create).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function upload(User $user): bool
    {
        return $this->create($user);
    }

    /**
     * Определить, может ли пользователь переобрабатывать медиа-файл.
     *
     * Требует права 'media.update'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function reprocess(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }

    /**
     * Определить, может ли пользователь перемещать медиа-файл.
     *
     * Требует права 'media.update'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Media $media Медиа-файл
     * @return bool
     */
    public function move(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }
}

