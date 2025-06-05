<?php

namespace App\Policies\Tenants;
use App\Models\Tenants\Payroll;
use App\Models\Tenants\User;

class PayrollPolicy
{
    /**
     * Determine whether the user can view any payroll records.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read payroll');
    }

    /**
     * Determine whether the user can view the payroll record.
     */
    public function view(User $user, Payroll $payroll): bool
    {
        return $user->can('read payroll');
    }

    /**
     * Determine whether the user can create payroll records.
     */
    public function create(User $user): bool
    {
        return $user->can('create payroll');
    }

    /**
     * Determine whether the user can update the payroll record.
     */
    public function update(User $user, Payroll $payroll): bool
    {
        return $user->can('update payroll');
    }

    /**
     * Determine whether the user can delete the payroll record.
     */
    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->can('delete payroll');
    }
}