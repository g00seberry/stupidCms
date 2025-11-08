<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('media.read');
    }

    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAdminPermission('media.create');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.delete');
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.restore');
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }

    public function upload(User $user): bool
    {
        return $this->create($user);
    }

    public function reprocess(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }

    public function move(User $user, Media $media): bool
    {
        return $user->hasAdminPermission('media.update');
    }
}

