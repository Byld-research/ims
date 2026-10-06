<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Draft purchase orders for items picked on the dashboard (SPEC 5.3a). Suggests a supplier,
 * price and quantity per item; one draft per site and supplier. Nothing touches stock: the
 * drafts follow the normal order lifecycle.
 */
class QuickOrder
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    /**
     * Suggested quantity (SPEC 5.3a): a two-bin item reorders one bin; any other item reorders
     * up to twice its minimum. Quantities already on orders in progress (drafts included) are
     * subtracted, and the result is rounded up to whole packs.
     */
    public static function suggestedQty(Stock $stock, string $inProgress, string $packSize): string
    {
        $need = $stock->is_kanban
            ? Decimal::sub($stock->bin_qty ?? '0', $inProgress)
            : Decimal::sub(Decimal::sub(Decimal::mul($stock->min_level, '2'), $stock->qty), $inProgress);

        if (Decimal::compare($need, '0') <= 0) {
            return '0.000';
        }

        if (Decimal::compare($packSize, '0') <= 0) {
            return Decimal::round($need, 3);
        }

        $packs = bcdiv($need, $packSize, 0);
        if (Decimal::compare(Decimal::mul($packs, $packSize), $need) < 0) {
            $packs = bcadd($packs, '1', 0);
        }

        return Decimal::round(Decimal::mul($packs, $packSize), 3);
    }

    /**
     * One row per stock row, worst-first order kept from the input.
     *
     * @param  Collection<int, Stock>  $stocks  with item and site loaded
     * @return Collection<int, array{stock: Stock, supplier_id: ?int, suppliers: Collection, prices: array<int, ?string>, packs: array<int, string>, in_progress: list<array>, qty: string, include: bool}>
     */
    public function suggest(Collection $stocks): Collection
    {
        $itemIds = $stocks->pluck('item_id')->unique()->values()->all();
        $inProgress = PurchaseOrderLine::inProgress($itemIds);
        $lastSupplier = $this->lastSuppliers($itemIds);

        $links = SupplierItem::query()
            ->with('supplier')
            ->whereIn('item_id', $itemIds)
            ->whereHas('supplier', fn ($q) => $q->active())
            ->get()
            ->groupBy('item_id');

        return $stocks->map(function (Stock $stock) use ($inProgress, $lastSupplier, $links) {
            $itemLinks = $links->get($stock->item_id, collect());
            $supplierId = $lastSupplier[$stock->item_id] ?? null;
            if (! $itemLinks->contains('supplier_id', $supplierId)) {
                $supplierId = $itemLinks->sortByDesc('updated_at')->first()?->supplier_id;
            }

            $orders = $inProgress[$stock->item_id][$stock->site_id] ?? [];
            $onOrders = array_reduce($orders, fn ($sum, $o) => Decimal::add($sum, $o['qty']), '0');
            $pack = (string) ($itemLinks->firstWhere('supplier_id', $supplierId)?->pack_size ?? '1');
            $qty = self::suggestedQty($stock, $onOrders, $pack);

            return [
                'stock' => $stock,
                'supplier_id' => $supplierId,
                'suppliers' => $itemLinks->pluck('supplier'),
                'prices' => $itemLinks->mapWithKeys(fn (SupplierItem $l) => [$l->supplier_id => $l->last_price])->all(),
                'packs' => $itemLinks->mapWithKeys(fn (SupplierItem $l) => [$l->supplier_id => (string) $l->pack_size])->all(),
                'in_progress' => $orders,
                'qty' => $qty,
                // An item already on an order starts unticked; ticking it again is a deliberate choice.
                'include' => $orders === [] && Decimal::compare($qty, '0') > 0,
            ];
        })->values();
    }

    /**
     * Creates one draft per site and supplier, all or nothing.
     *
     * @param  list<array{stock: Stock, supplier: Supplier, qty: string, unit_price: ?string}>  $lines
     * @return list<PurchaseOrder>
     */
    public function place(array $lines, User $user): array
    {
        return DB::transaction(function () use ($lines, $user) {
            $groups = collect($lines)->groupBy(fn ($line) => $line['stock']->site_id.':'.$line['supplier']->id);
            $created = [];

            foreach ($groups as $group) {
                $first = $group->first();
                /** @var Site $site */
                $site = $first['stock']->site;
                $order = $this->orders->create($first['supplier'], $site, $user, __('Created from the dashboard.'));

                foreach ($group as $line) {
                    /** @var Item $item */
                    $item = $line['stock']->item;
                    $this->orders->addLine($order, $item, $line['qty'], $line['unit_price']);
                }

                $created[] = $order;
            }

            return $created;
        });
    }

    /**
     * The supplier each item was last ordered from (drafts and cancelled orders do not count).
     *
     * @param  list<int>  $itemIds
     * @return array<int, int> item id => supplier id
     */
    private function lastSuppliers(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = PurchaseOrderLine::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereIn('purchase_order_lines.item_id', $itemIds)
            ->whereNotIn('purchase_orders.status', [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled])
            ->orderByDesc('purchase_orders.ordered_at')
            ->orderByDesc('purchase_orders.id')
            ->toBase()
            ->get(['purchase_order_lines.item_id', 'purchase_orders.supplier_id']);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->item_id] ??= (int) $row->supplier_id;
        }

        return $result;
    }
}
