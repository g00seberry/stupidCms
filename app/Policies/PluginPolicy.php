<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plugin;
use App\Models\User;

final class PluginPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('plugins.read');
    }

    public function toggle(User $user, Plugin $plugin): bool
    {
        return $user->hasAdminPermission('plugins.toggle');
    }

    public function sync(User $user): bool
    {
        return $user->hasAdminPermission('plugins.sync');
    }
}

