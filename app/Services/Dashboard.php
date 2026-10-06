<?php

namespace App\Services;

use App\Enums\Criticality;
use App\Enums\PurchaseOrderStatus as PoStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alerts and figures for one site, or all sites when $site is null (SPEC 8).
 * Dates follow the site's time zone; the consolidated view uses UTC.
 */
class Dashboard
{
    /** Rows shown per alert; the rest is summarised as "and N more". */
    public const ALERT_ROWS = 8;

    private readonly string $timezone;

    public function __construct(private readonly ?Site $site, private readonly int $rowLimit = self::ALERT_ROWS)
    {
        $this->timezone = $site?->timezone ?? 'UTC';
    }

    /**
     * @return array<string, array{total: int, rows: Collection}>
     */
    public function alerts(): array
    {
        return [
            'below' => $this->limited($this->stocks()->needsReplenishment()->where('is_kanban', false)->where('qty', '>', 0)),
            'out' => $this->limited($this->stocks()->needsReplenishment()->where('is_kanban', false)->where('qty', 0)),
            'kanban' => $this->limited($this->stocks()->needsReplenishment()->where('is_kanban', true)),
            'late' => $this->limited($this->orders()
                ->whereIn('status', [PoStatus::Ordered, PoStatus::Confirmed, PoStatus::Shipped])
                ->where('eta', '<', $this->today()->toDateString())
                ->whereDoesntHave('lines', fn ($q) => $q->where('qty_received', '>', 0))
                ->orderBy('eta')),
            'unconfirmed' => $this->limited($this->orders()->where('status', PoStatus::Ordered)->orderBy('ordered_at')),
            'stalled' => $this->limited($this->orders()
                ->where('status', PoStatus::PartiallyReceived)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('stock_transactions')
                    ->join('purchase_order_lines as l', 'l.id', '=', 'stock_transactions.purchase_order_line_id')
                    ->whereColumn('l.purchase_order_id', 'purchase_orders.id')
                    ->groupBy('l.purchase_order_id')
                    ->havingRaw('MIN(stock_transactions.created_at) < ?', [now()->subDays(30)]))
                ->orderBy('id')),
            'due' => ['total' => $this->stocks()->dueForCount()->count(), 'rows' => collect()],
        ];
    }

    /**
     * Quantities on open orders for the stock rows shown in the alerts (SPEC 5.5).
     *
     * @param  array<string, array{total: int, rows: Collection}>  $alerts
     * @return array<int, array<int, string>>
     */
    public function onOrderFor(array $alerts): array
    {
        $itemIds = collect(['below', 'out', 'kanban'])->flatMap(fn ($key) => $alerts[$key]['rows']->pluck('item_id'))->unique()->values()->all();

        return PurchaseOrderLine::onOrder($itemIds, $this->site?->id);
    }

    public function totalValue(): string
    {
        return (string) ($this->scopedStock()->selectRaw('COALESCE(SUM(qty * avg_cost), 0) AS v')->value('v') ?? '0');
    }

    /**
     * Stock value per top-level category, largest first.
     *
     * @return Collection<int, object{name: string, value: string}>
     */
    public function valueByCategory(): Collection
    {
        return $this->scopedStock()
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->join('categories as c', 'c.id', '=', 'items.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->groupByRaw('COALESCE(p.id, c.id), COALESCE(p.name, c.name)')
            ->selectRaw('COALESCE(p.name, c.name) AS name, SUM(stocks.qty * stocks.avg_cost) AS value')
            ->havingRaw('value > 0')
            ->orderByDesc('value')
            ->toBase()
            ->get();
    }

    /**
     * @return array{with_minimum: int, below: int}
     */
    public function minimumCoverage(): array
    {
        return [
            'with_minimum' => $this->stocks()->where(fn ($q) => $q->where('min_level', '>', 0)->orWhere('is_kanban', true))->count(),
            'below' => $this->stocks()->needsReplenishment()->count(),
        ];
    }

    /**
     * Value issued this calendar month and last, in the site's time zone (SPEC 8).
     *
     * @return array{current: string, previous: string, month: string, previous_month: string}
     */
    public function consumption(): array
    {
        $start = $this->today()->startOfMonth();
        $previousStart = $start->copy()->subMonthNoOverflow();

        return [
            'current' => $this->issuedValue($start, $this->today()->endOfDay()),
            'previous' => $this->issuedValue($previousStart, $start),
            'month' => $start->format('F'),
            'previous_month' => $previousStart->format('F'),
        ];
    }

    /**
     * The ten machines with the highest consumption value over the last 90 days (SPEC 8).
     *
     * @return Collection<int, object{machine_id: int, sku: string, name: string, revision: string, site: string, issues: int, value: string}>
     */
    public function topMachines(): Collection
    {
        return StockTransaction::query()
            ->join('machines', 'machines.id', '=', 'stock_transactions.machine_id')
            ->join('sites', 'sites.id', '=', 'stock_transactions.site_id')
            ->where('stock_transactions.type', TransactionType::IssueMachine)
            ->where('stock_transactions.created_at', '>=', now()->subDays(90))
            ->when($this->site, fn ($q, $site) => $q->where('stock_transactions.site_id', $site->id))
            ->groupBy('machines.id', 'machines.sku', 'machines.name', 'machines.revision', 'sites.code')
            ->selectRaw('machines.id AS machine_id, machines.sku, machines.name, machines.revision, sites.code AS site,
                COUNT(*) AS issues, -SUM(stock_transactions.value) AS value')
            ->orderByDesc('value')
            ->limit(10)
            ->toBase()
            ->get();
    }

    /**
     * Items in stock with no movement at their site for 12 months (SPEC 8).
     *
     * @return array{count: int, value: string, rows: Collection}
     */
    public function dormant(): array
    {
        $query = fn () => $this->scopedStock()
            ->where('stocks.qty', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_transactions as t')
                ->whereColumn('t.item_id', 'stocks.item_id')
                ->whereColumn('t.site_id', 'stocks.site_id')
                ->where('t.created_at', '>=', now()->subMonths(12)));

        return [
            'count' => $query()->count(),
            'value' => (string) ($query()->selectRaw('COALESCE(SUM(qty * avg_cost), 0) AS v')->value('v') ?? '0'),
            'rows' => $query()->with(['item', 'site'])->orderByRaw('qty * avg_cost DESC')->limit(5)->get(),
        ];
    }

    /**
     * Every stock row that needs replenishment, worst first: out of stock, then high criticality,
     * then the lowest share of its minimum (or bin) left. Severity follows the status palette.
     *
     * @return array{total: int, rows: Collection<int, array{stock: Stock, severity: string, ratio: float}>}
     */
    public function attention(int $limit = 15): array
    {
        $rows = $this->stocks()->needsReplenishment()->get()->map(function (Stock $stock) {
            $threshold = (float) ($stock->is_kanban ? $stock->bin_qty : $stock->min_level);
            $ratio = $threshold > 0 ? (float) $stock->qty / $threshold : 0.0;

            $severity = match (true) {
                bccomp($stock->qty, '0', 3) === 0 => 'critical',
                $stock->is_kanban => 'warning',
                $stock->item->criticality === Criticality::High => 'serious',
                default => 'warning',
            };

            return ['stock' => $stock, 'severity' => $severity, 'ratio' => $ratio];
        });

        $rank = ['critical' => 0, 'serious' => 1, 'warning' => 2];
        $sorted = $rows->sortBy([
            fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']],
            fn ($a, $b) => ($a['stock']->item->criticality?->rank() ?? 9) <=> ($b['stock']->item->criticality?->rank() ?? 9),
            fn ($a, $b) => $a['ratio'] <=> $b['ratio'],
        ])->values();

        return ['total' => $sorted->count(), 'rows' => $sorted->take($limit)->values()];
    }

    /**
     * Items issued most (by value) over the last 30 days, with their stock row at the site.
     * Moving items without a minimum level are the ones nobody is watching.
     *
     * @return Collection<int, array{stock: ?Stock, item_id: int, site_id: int, qty: string, value: string}>
     */
    public function movers(int $limit = 8): Collection
    {
        $usage = StockTransaction::query()
            ->whereIn('type', [TransactionType::IssueMachine, TransactionType::IssueGeneral])
            ->where('created_at', '>=', now()->subDays(30))
            ->when($this->site, fn ($q, $site) => $q->where('site_id', $site->id))
            ->groupBy('item_id', 'site_id')
            ->selectRaw('item_id, site_id, -SUM(qty_delta) AS qty, -SUM(value) AS value')
            ->orderByDesc('value')
            ->limit($limit)
            ->toBase()
            ->get();

        $stocks = Stock::query()->with(['item', 'site'])
            ->whereIn('item_id', $usage->pluck('item_id'))
            ->when($this->site, fn ($q, $site) => $q->where('site_id', $site->id))
            ->get()
            ->keyBy(fn (Stock $s) => $s->item_id.':'.$s->site_id);

        return $usage->map(fn ($row) => [
            'stock' => $stocks->get($row->item_id.':'.$row->site_id),
            'item_id' => (int) $row->item_id,
            'site_id' => (int) $row->site_id,
            'qty' => (string) $row->qty,
            'value' => (string) $row->value,
        ])->filter(fn ($row) => $row['stock'] !== null)->values();
    }

    /**
     * Quantity issued per week for the last $weeks weeks, oldest first, per item and site.
     * Read as "is this item moving?", not as a forecast.
     *
     * @param  list<int>  $itemIds
     * @return array<string, list<float>> "item_id:site_id" => weekly quantities
     */
    public function weeklyUsage(array $itemIds, int $weeks = 12): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = StockTransaction::query()
            ->whereIn('type', [TransactionType::IssueMachine, TransactionType::IssueGeneral])
            ->whereIn('item_id', $itemIds)
            ->where('created_at', '>=', now()->subWeeks($weeks))
            ->when($this->site, fn ($q, $site) => $q->where('site_id', $site->id))
            ->groupBy('item_id', 'site_id', 'weeks_ago')
            ->selectRaw('item_id, site_id, FLOOR(TIMESTAMPDIFF(DAY, created_at, ?) / 7) AS weeks_ago, -SUM(qty_delta) AS qty', [now()])
            ->toBase()
            ->get();

        $series = [];
        foreach ($rows as $row) {
            $key = $row->item_id.':'.$row->site_id;
            $series[$key] ??= array_fill(0, $weeks, 0.0);
            $index = $weeks - 1 - min($weeks - 1, (int) $row->weeks_ago);
            $series[$key][$index] += (float) $row->qty;
        }

        return $series;
    }

    /**
     * Late, unconfirmed and stalled orders in one list, each with why it is listed.
     *
     * @param  array<string, array{total: int, rows: Collection}>  $alerts
     * @return Collection<int, array{order: PurchaseOrder, reason: string, severity: string}>
     */
    public function ordersToChase(array $alerts): Collection
    {
        return collect([
            ['late', 'serious'], ['stalled', 'warning'], ['unconfirmed', 'warning'],
        ])->flatMap(fn ($pair) => $alerts[$pair[0]]['rows']->map(fn ($order) => ['order' => $order, 'reason' => $pair[0], 'severity' => $pair[1]]))
            ->unique(fn ($row) => $row['order']->id)
            ->values();
    }

    private function issuedValue(Carbon $from, Carbon $to): string
    {
        $value = StockTransaction::query()
            ->whereIn('type', [TransactionType::IssueMachine, TransactionType::IssueGeneral])
            ->where('created_at', '>=', $from->copy()->utc())
            ->where('created_at', '<', $to->copy()->utc())
            ->when($this->site, fn ($q, $site) => $q->where('site_id', $site->id))
            ->sum(DB::raw('-value'));

        return Decimal::round((string) $value, 4);
    }

    /**
     * Stock rows for alerts: active items, with item and site loaded, high criticality first (SPEC 8).
     */
    private function stocks(): Builder
    {
        return Stock::query()
            ->select('stocks.*')
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->where('items.is_active', true)
            ->when($this->site, fn ($q, $site) => $q->where('stocks.site_id', $site->id))
            ->with(['item', 'site'])
            ->orderByRaw(Criticality::orderSql('items.criticality'))
            ->orderBy('items.sku');
    }

    private function scopedStock(): Builder
    {
        return Stock::query()->when($this->site, fn ($q, $site) => $q->where('stocks.site_id', $site->id));
    }

    private function orders(): Builder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'site'])
            ->when($this->site, fn ($q, $site) => $q->where('site_id', $site->id));
    }

    /**
     * @return array{total: int, rows: Collection}
     */
    private function limited(Builder $query): array
    {
        return ['total' => (clone $query)->count(), 'rows' => $query->limit($this->rowLimit)->get()];
    }

    private function today(): Carbon
    {
        return now($this->timezone)->startOfDay();
    }
}
