<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MediaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }

    // Кастомные abilities
    /**
     * Determine whether the user can upload media.
     * Проверяется на уровне класса (Media::class), без привязки к конкретному инстансу.
     */
    public function upload(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can reprocess media variants.
     */
    public function reprocess(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can move media between storages/folders.
     */
    public function move(User $user, Media $media): bool
    {
        return false;
    }
}
