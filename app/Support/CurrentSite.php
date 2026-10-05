<?php

namespace App\Support;

use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The site selected in the header (SPEC 7). Managers and operators are fixed to their
 * own site; administrators may switch between sites or the consolidated view.
 */
class CurrentSite
{
    public const SESSION_KEY = 'current_site_id';

    public const ALL = 'all';

    private Site|false|null $resolved = null;

    public function __construct(
        private readonly Session $session,
        private readonly ?User $user,
    ) {}

    /**
     * The selected site, or null for the administrators' consolidated view.
     */
    public function get(): ?Site
    {
        if ($this->resolved === null) {
            $this->resolved = $this->resolve() ?? false;
        }

        return $this->resolved ?: null;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function isConsolidated(): bool
    {
        return $this->get() === null;
    }

    public function canSwitch(): bool
    {
        return (bool) $this->user?->isAdmin();
    }

    /**
     * @return Collection<int, Site>
     */
    public function options(): Collection
    {
        return Site::query()->active()->orderBy('code')->get();
    }

    public function switchTo(?Site $site): void
    {
        $this->session->put(self::SESSION_KEY, $site?->id ?? self::ALL);
        $this->resolved = null;
    }

    /**
     * Time zone for displaying timestamps; UTC in the consolidated view.
     */
    public function timezone(): string
    {
        return $this->get()?->timezone ?? 'UTC';
    }

    private function resolve(): ?Site
    {
        if ($this->user === null) {
            return null;
        }

        if (! $this->user->isAdmin()) {
            return $this->user->site;
        }

        $selected = $this->session->get(self::SESSION_KEY);

        if ($selected === self::ALL) {
            return null;
        }

        return ($selected ? Site::query()->active()->find($selected) : null)
            ?? Site::query()->active()->orderBy('code')->first();
    }
}
