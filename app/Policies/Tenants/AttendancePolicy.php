<?php

namespace App\Policies\Tenants;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read attendance');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('read attendance') || $user->id === $attendance->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('create attendance');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('update attendance') || $user->id === $attendance->user_id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('delete attendance') || $user->id === $attendance->user_id;
    }
}
