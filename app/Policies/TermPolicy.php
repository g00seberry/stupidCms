<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Политика авторизации для Term.
 *
 * Все методы возвращают false (термы управляются через EntryPolicy).
 * Политика оставлена для совместимости с Laravel Gate.
 *
 * @package App\Policies
 */
class TermPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые термы.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь просматривать терм.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function view(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь создавать термы.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь обновлять терм.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function update(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь удалять терм.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function delete(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь восстанавливать терм.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function restore(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь окончательно удалять терм.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function forceDelete(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Определить, может ли пользователь привязывать записи к терму.
     *
     * Всегда возвращает false (термы управляются через EntryPolicy).
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Term $term Терм
     * @return bool
     */
    public function attachEntry(User $user, Term $term): bool
    {
        return false;
    }
}
