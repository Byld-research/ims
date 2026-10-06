<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Collection;

/**
 * Contents of one daily digest (SPEC 5.9): items below minimum, kanban refills, orders past
 * their ETA with nothing received, and orders never confirmed. Null site means all sites.
 */
class Digest
{
    /** Rows per section in the email; the rest is summarised as "and N more". */
    public const ROWS = 25;

    /** @var array<string, array{total: int, rows: Collection}> */
    public readonly array $sections;

    /** @var array<int, array<int, string>> */
    public readonly array $onOrder;

    public function __construct(public readonly ?Site $site)
    {
        $dashboard = new Dashboard($site, self::ROWS);
        $alerts = $dashboard->alerts();

        // Out of stock is "below minimum" too; the email keeps one list, emptiest first.
        $below = $alerts['out']['rows']->concat($alerts['below']['rows'])->take(self::ROWS);

        $this->sections = [
            'below' => ['total' => $alerts['out']['total'] + $alerts['below']['total'], 'rows' => $below],
            'kanban' => $alerts['kanban'],
            'late' => $alerts['late'],
            'unconfirmed' => $alerts['unconfirmed'],
        ];
        $this->onOrder = $dashboard->onOrderFor($alerts);
    }

    public function isEmpty(): bool
    {
        return collect($this->sections)->sum('total') === 0;
    }

    public function summary(): string
    {
        $parts = array_filter([
            $this->sections['below']['total'] ? trans_choice('{1} 1 item below minimum|[2,*] :count items below minimum', $this->sections['below']['total'], ['count' => $this->sections['below']['total']]) : null,
            $this->sections['kanban']['total'] ? trans_choice('{1} 1 two-bin refill|[2,*] :count two-bin refills', $this->sections['kanban']['total'], ['count' => $this->sections['kanban']['total']]) : null,
            ($orders = $this->sections['late']['total'] + $this->sections['unconfirmed']['total'])
                ? trans_choice('{1} 1 order to chase|[2,*] :count orders to chase', $orders, ['count' => $orders]) : null,
        ]);

        return implode(', ', $parts);
    }
}
