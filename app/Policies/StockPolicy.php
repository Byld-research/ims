<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\Stock;
use App\Models\User;

/**
 * Stock movements are authorised against the site whose stock changes.
 * Called as Gate::authorize('adjust', [Stock::class, $site]).
 */
class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Stock $stock): bool
    {
        return true;
    }

    public function setLevels(User $user, Site $site): bool
    {
        return $user->canWriteSite($site);
    }

    public function adjust(User $user, Site $site): bool
    {
        return $user->canWriteSite($site);
    }

    public function issue(User $user, Site $site): bool
    {
        return $user->canWriteSite($site);
    }

    /**
     * The one cross-site write (SPEC 5.2): the receiving manager records the transfer,
     * which also writes TRANSFER_OUT at the sending site.
     */
    public function transfer(User $user, Site $from, Site $to): bool
    {
        return $from->id !== $to->id && $user->canWriteSite($to);
    }
}
