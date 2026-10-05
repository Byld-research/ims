<?php

namespace App\Policies;

use App\Models\MachineType;
use App\Models\User;

class MachineTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canEditMasterData();
    }

    public function view(User $user, MachineType $machineType): bool
    {
        return $user->canEditMasterData();
    }

    public function create(User $user): bool
    {
        return $user->canEditMasterData();
    }

    public function update(User $user, MachineType $machineType): bool
    {
        return $user->canEditMasterData();
    }
}
