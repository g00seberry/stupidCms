<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    // Кастомные abilities
    /**
     * Determine whether the user can publish/unpublish the entry.
     */
    public function publish(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can attach media to the entry.
     */
    public function attachMedia(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }

    /**
     * Determine whether the user can manage terms for the entry.
     */
    public function manageTerms(User $user, Entry $entry): bool
    {
        return $user->hasAdminPermission('manage.entries');
    }
}
