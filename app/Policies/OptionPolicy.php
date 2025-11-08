<?php

namespace App\Policies;

use App\Models\Option;
use App\Models\User;

class OptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('options.read');
    }

    public function view(User $user, Option $option): bool
    {
        return $this->viewAny($user);
    }

    public function write(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.write');
    }

    public function delete(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.delete');
    }

    public function restore(User $user, Option $option): bool
    {
        return $user->hasAdminPermission('options.restore');
    }
}

