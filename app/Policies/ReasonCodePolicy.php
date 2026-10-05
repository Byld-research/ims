<?php

namespace App\Policies;

use App\Models\ReasonCode;
use App\Models\User;

/**
 * Administration only (SPEC 6).
 */
class ReasonCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ReasonCode $reasonCode): bool
    {
        return $user->isAdmin();
    }
}
