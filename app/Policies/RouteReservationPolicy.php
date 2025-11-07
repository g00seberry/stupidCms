<?php

namespace App\Policies;

use App\Models\ReservedRoute;
use App\Models\User;

class RouteReservationPolicy
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
    public function view(User $user, ReservedRoute $reservedRoute): bool
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
    public function update(User $user, ReservedRoute $reservedRoute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReservedRoute $reservedRoute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete any model (for collection operations).
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}

