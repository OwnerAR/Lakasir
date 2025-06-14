<?php

namespace App\Policies\Tenants;

use App\Models\Tenants\WorkSchedule;
use App\Models\Tenants\User;

class WorkSchedulePolicy
{
    /**
     * Determine whether the user can view any work schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read work schedule');
    }

    /**
     * Determine whether the user can view the work schedule.
     */
    public function view(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can('read work schedule');
    }

    /**
     * Determine whether the user can create work schedules.
     */
    public function create(User $user): bool
    {
        return $user->can('create work schedule');
    }

    /**
     * Determine whether the user can update the work schedule.
     */
    public function update(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can('update work schedule');
    }

    /**
     * Determine whether the user can delete the work schedule.
     */
    public function delete(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can('delete work schedule');
    }
}