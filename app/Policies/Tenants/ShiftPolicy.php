<?php

namespace App\Policies\Tenants;

use App\Models\Tenants\Shift;
use App\Models\Tenants\User;

class ShiftPolicy
{
    /**
     * Determine whether the user can view any shifts.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read shift');
    }

    /**
     * Determine whether the user can view the shift.
     */
    public function view(User $user, Shift $shift): bool
    {
        return $user->can('read shift');
    }

    /**
     * Determine whether the user can create shifts.
     */
    public function create(User $user): bool
    {
        return $user->can('create shift');
    }

    /**
     * Determine whether the user can update the shift.
     */
    public function update(User $user, Shift $shift): bool
    {
        return $user->can('update shift');
    }

    /**
     * Determine whether the user can delete the shift.
     */
    public function delete(User $user, Shift $shift): bool
    {
        return $user->can('delete shift');
    }
}