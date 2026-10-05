<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $user, Site $site): bool
    {
        return $user->canWriteSite($site);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->canWriteSite($purchaseOrder->site_id);
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->canWriteSite($purchaseOrder->site_id);
    }
}
