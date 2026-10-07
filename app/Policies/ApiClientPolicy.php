<?php

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

/**
 * API clients are managed by administrators only (SPEC 7a).
 */
class ApiClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ApiClient $apiClient): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ApiClient $apiClient): bool
    {
        return $user->isAdmin();
    }
}
