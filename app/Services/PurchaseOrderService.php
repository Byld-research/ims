<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus as Status;
use App\Exceptions\PurchaseOrderException;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The purchase order lifecycle (SPEC 5.3). Every status change goes through here and follows
 * PurchaseOrderStatus::transitions(), which mirrors the state diagram.
 *
 * Locking order, always: purchase_orders row, then purchase_order_lines rows, then stocks rows
 * (inside StockService). Receiving and closing short lock the order first, so two people working
 * on the same order are serialised instead of double-counting (SPEC 13.7).
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    public function create(Supplier $supplier, Site $site, User $user, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $site, $user, $notes) {
            $order = new PurchaseOrder(['supplier_id' => $supplier->id, 'site_id' => $site->id, 'notes' => $notes]);
            $order->number = $this->numbers->next('PO', $this->today($site)->year);
            $order->status = Status::Draft;
            $order->created_by = $user->id;
            $order->save();

            return $order;
        });
    }

    /**
     * A new line, priced from the supplier's last price when no price is given (SPEC 4.9).
     */
    public function addLine(PurchaseOrder $order, Item $item, string $qty, ?string $unitPrice): PurchaseOrderLine
    {
        $this->assertDraft($order);

        $unitPrice ??= SupplierItem::query()
            ->where('supplier_id', $order->supplier_id)
            ->where('item_id', $item->id)
            ->value('last_price');

        if ($unitPrice === null) {
            throw new PurchaseOrderException(__('Enter a price: :supplier has no last price for :sku.', [
                'supplier' => $order->supplier->name, 'sku' => $item->sku,
            ]), 'unit_price');
        }

        return $order->lines()->create(['item_id' => $item->id, 'qty_ordered' => $qty, 'unit_price' => $unitPrice]);
    }

    public function updateLine(PurchaseOrderLine $line, string $qty, string $unitPrice): void
    {
        $this->assertDraft($line->purchaseOrder);

        $line->update(['qty_ordered' => $qty, 'unit_price' => $unitPrice]);
    }

    public function removeLine(PurchaseOrderLine $line): void
    {
        $this->assertDraft($line->purchaseOrder);

        $line->delete();
    }

    /**
     * DRAFT → ORDERED: sent to the supplier. The prices sent become the supplier's last prices.
     */
    public function markOrdered(PurchaseOrder $order): void
    {
        $this->transition($order, Status::Ordered, function (PurchaseOrder $order) {
            if (! $order->lines()->exists()) {
                throw new PurchaseOrderException(__('Add at least one line before sending the order.'));
            }

            $order->ordered_at = $this->today($order->site);

            foreach ($order->lines as $line) {
                SupplierItem::query()->updateOrCreate(
                    ['supplier_id' => $order->supplier_id, 'item_id' => $line->item_id],
                    ['last_price' => $line->unit_price],
                );
            }
        });
    }

    /**
     * ORDERED → CONFIRMED: the supplier confirms and gives a delivery date (step 6).
     */
    public function confirm(PurchaseOrder $order, Carbon $eta): void
    {
        $this->transition($order, Status::Confirmed, function (PurchaseOrder $order) use ($eta) {
            $order->confirmed_at = $this->today($order->site);
            $order->eta = $eta;
        });
    }

    /**
     * CONFIRMED → SHIPPED: the supplier ships and provides tracking (step 7).
     */
    public function ship(PurchaseOrder $order, string $trackingRef): void
    {
        $this->transition($order, Status::Shipped, function (PurchaseOrder $order) use ($trackingRef) {
            $order->shipped_at = $this->today($order->site);
            $order->tracking_ref = $trackingRef;
        });
    }

    /**
     * Only while nothing has been received (SPEC 5.3.6).
     */
    public function cancel(PurchaseOrder $order, User $user, string $reason): void
    {
        $this->transition($order, Status::Cancelled, function (PurchaseOrder $order) use ($user, $reason) {
            if ($order->lines()->where('qty_received', '>', 0)->exists()) {
                throw new PurchaseOrderException(__('Goods have been received on this order, so it cannot be cancelled. Close the remaining lines short instead.'));
            }

            $this->appendNote($order, $user, __('Cancelled: :reason', ['reason' => $reason]));
        });
    }

    /**
     * RECEIVED → CLOSED: payment settled elsewhere; no accounting meaning (SPEC 5.3.5).
     */
    public function close(PurchaseOrder $order): void
    {
        $this->transition($order, Status::Closed, function (PurchaseOrder $order) {
            $order->closed_at = $this->today($order->site);
        });
    }

    /**
     * Receive goods against one or more lines (SPEC 5.3.1–4).
     *
     * @param  array<int, string>  $quantities  line id => quantity received now (positive)
     * @return list<array{line: PurchaseOrderLine, excess: string}> over-received lines, for the warning
     */
    public function receive(PurchaseOrder $order, array $quantities, User $user): array
    {
        return DB::transaction(function () use ($order, $quantities, $user) {
            $order = $this->lock($order);

            if (! $order->status->acceptsReceipts()) {
                throw new PurchaseOrderException(__('Goods can be received only while the order is ordered, confirmed, shipped or partially received. It is :status.', [
                    'status' => strtolower($order->status->label()),
                ]));
            }

            $lines = $order->lines()->with('item')->whereKey(array_keys($quantities))->lockForUpdate()->get()->keyBy('id');
            $overReceived = [];

            foreach ($quantities as $lineId => $qty) {
                $line = $lines->get($lineId) ?? throw new PurchaseOrderException(__('A line does not belong to this order.'));

                if ($line->is_closed) {
                    throw new PurchaseOrderException(__('Line :sku was closed short and cannot receive more.', ['sku' => $line->item->sku]));
                }

                $outstanding = $line->outstanding();
                $this->stock->receipt($line, $order->site, $qty, $user);

                $line->qty_received = Decimal::round(Decimal::add($line->qty_received, $qty), 3);
                $line->save();

                if (Decimal::compare($qty, $outstanding) > 0) {
                    $overReceived[] = ['line' => $line, 'excess' => Decimal::round(Decimal::sub($qty, $outstanding), 3)];
                }
            }

            $this->settleStatus($order);

            return $overReceived;
        }, StockService::ATTEMPTS);
    }

    /**
     * Close a line short: the rest will not come. No stock movement (SPEC 5.3.3).
     */
    public function closeLineShort(PurchaseOrderLine $line, User $user, string $reason): void
    {
        DB::transaction(function () use ($line, $user, $reason) {
            $order = $this->lock($line->purchaseOrder);
            $line = $order->lines()->with('item')->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if (! $order->status->acceptsReceipts()) {
                throw new PurchaseOrderException(__('Lines can be closed short only while goods are still expected.'));
            }

            if ($line->isFulfilled()) {
                throw new PurchaseOrderException(__('Line :sku has nothing outstanding.', ['sku' => $line->item->sku]));
            }

            $this->appendNote($order, $user, __('Line :sku closed short, :qty :uom not delivered: :reason', [
                'sku' => $line->item->sku, 'qty' => Format::qty($line->outstanding()), 'uom' => $line->item->uom, 'reason' => $reason,
            ]));

            $line->is_closed = true;
            $line->save();

            $this->settleStatus($order);
        }, StockService::ATTEMPTS);
    }

    /**
     * RECEIVED once every line is received in full or closed; otherwise PARTIALLY_RECEIVED
     * as soon as anything arrived (SPEC 5.3.2).
     */
    private function settleStatus(PurchaseOrder $order): void
    {
        $lines = $order->lines()->get();

        $target = match (true) {
            $lines->every(fn (PurchaseOrderLine $l) => $l->isFulfilled()) => Status::Received,
            $lines->contains(fn (PurchaseOrderLine $l) => Decimal::compare($l->qty_received, '0') > 0) => Status::PartiallyReceived,
            default => $order->status,
        };

        if ($target !== $order->status) {
            $this->assertTransition($order, $target);
            $order->status = $target;
        }

        $order->save();
    }

    private function transition(PurchaseOrder $order, Status $target, callable $apply): void
    {
        DB::transaction(function () use ($order, $target, $apply) {
            $locked = $this->lock($order);
            $this->assertTransition($locked, $target);

            $apply($locked);

            $locked->status = $target;
            $locked->save();

            $order->setRawAttributes($locked->getAttributes(), true);
        }, StockService::ATTEMPTS);
    }

    private function assertTransition(PurchaseOrder $order, Status $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new PurchaseOrderException(__('An order that is :from cannot become :to.', [
                'from' => strtolower($order->status->label()), 'to' => strtolower($target->label()),
            ]));
        }
    }

    private function assertDraft(PurchaseOrder $order): void
    {
        if ($order->status !== Status::Draft) {
            throw new PurchaseOrderException(__('Lines can be changed only while the order is a draft.'));
        }
    }

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->with('site')->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function appendNote(PurchaseOrder $order, User $user, string $text): void
    {
        $stamp = now($order->site->timezone)->format('Y-m-d H:i').' '.$user->name;

        $order->notes = trim(($order->notes ? $order->notes."\n" : '')."[{$stamp}] {$text}");
    }

    private function today(Site $site): Carbon
    {
        return now($site->timezone)->startOfDay();
    }
}
