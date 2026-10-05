<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/**
 * Administration only (SPEC 6).
 */
class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }
}
