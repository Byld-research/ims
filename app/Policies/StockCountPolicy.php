<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\StockCount;
use App\Models\User;

class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canEditMasterData();
    }

    public function view(User $user, StockCount $stockCount): bool
    {
        return $user->canEditMasterData();
    }

    public function create(User $user, Site $site): bool
    {
        return $user->canWriteSite($site);
    }

    public function update(User $user, StockCount $stockCount): bool
    {
        return $user->canWriteSite($stockCount->site_id);
    }

    public function post(User $user, StockCount $stockCount): bool
    {
        return $user->canWriteSite($stockCount->site_id);
    }
}
