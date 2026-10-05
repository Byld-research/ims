<?php

namespace App\Policies;

use App\Models\Machine;
use App\Models\User;

/**
 * Everyone reads the register; only administrators register, edit and relocate machines (SPEC 6).
 */
class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Machine $machine): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Machine $machine): bool
    {
        return $user->isAdmin();
    }
}
