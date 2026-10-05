<?php

namespace App\Services;

use App\Enums\ReasonCodeScope;
use App\Enums\TransactionType;
use App\Exceptions\StockException;
use App\Models\Item;
use App\Models\Machine;
use App\Models\PurchaseOrderLine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\StockTransaction;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only code that changes stock (SPEC 2, 5.1, 5.2).
 *
 * Every movement locks the stocks row, recalculates quantity and moving average cost,
 * writes one ledger row and updates the stocks row, inside one database transaction.
 *
 * Averaging: every incoming movement applies (qty × avg + in × cost) / (qty + in), rounded
 * half-up to 4 places; into empty stock the average becomes the incoming cost. A movement
 * that enters at the current average leaves it unchanged by construction, so replay() can
 * rebuild the stocks row from the ledger without knowing whether a cost was entered.
 */
class StockService
{
    /** A deadlock between concurrent movements is retried, not shown to the user. */
    public const ATTEMPTS = 3;

    /**
     * Manual correction or opening balance (SPEC 5.1, 5.2, 5.6).
     *
     * An increase uses $unitCost when given (and moves the average), otherwise the current
     * average; with no average yet, a cost is required. A decrease is valued at the average.
     */
    public function adjust(
        Item $item,
        Site $site,
        bool $increase,
        string $qty,
        ReasonCode $reason,
        User $user,
        ?string $unitCost = null,
        ?string $note = null,
        ?StockCount $stockCount = null,
    ): StockTransaction {
        if ($reason->applies_to !== ReasonCodeScope::Adjustment) {
            throw new InvalidArgumentException('An adjustment needs an adjustment reason code.');
        }

        return DB::transaction(function () use ($item, $site, $increase, $qty, $reason, $user, $unitCost, $note, $stockCount) {
            $transaction = $this->post(
                type: TransactionType::Adjustment,
                item: $item,
                site: $site,
                qtyDelta: $increase ? $this->positive($qty) : Decimal::negate($this->positive($qty)),
                user: $user,
                incomingCost: $increase ? $unitCost : null,
                references: [
                    'reason_code_id' => $reason->id,
                    'stock_count_id' => $stockCount?->id,
                    'note' => $note,
                ],
            );

            // An opening balance is a physical count (SPEC 5.6.9).
            if ($reason->code === ReasonCode::OPENING) {
                Stock::query()->where('item_id', $item->id)->where('site_id', $site->id)->update(['last_counted_at' => now()]);
            }

            return $transaction;
        }, self::ATTEMPTS);
    }

    /**
     * Bring stock to a counted quantity (SPEC 5.6.4–6). The difference is taken against the
     * live quantity under the row lock, not against the snapshot on the count line, because stock
     * may have moved during counting. Writes a COUNT adjustment only when there is a difference,
     * and always stamps last_counted_at. Callers run it inside DB::transaction.
     *
     * @param  string|null  $unitCost  required only when stock rises from nothing with no average yet
     */
    public function countTo(Item $item, Site $site, string $counted, StockCount $stockCount, User $user, ?string $unitCost = null, ?string $note = null): ?StockTransaction
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Posting a count line must run inside the transaction that posts the count.');
        }

        if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $counted)) {
            throw new InvalidArgumentException("Counted quantity must be zero or positive with at most 3 decimals, got [{$counted}].");
        }

        $stock = $this->lockedStock($item, $site);
        $difference = Decimal::sub($counted, $stock->qty);
        $transaction = null;

        if (! Decimal::isZero($difference)) {
            $transaction = $this->post(
                type: TransactionType::Adjustment,
                item: $item,
                site: $site,
                qtyDelta: $difference,
                user: $user,
                incomingCost: Decimal::compare($difference, '0') > 0 ? $unitCost : null,
                references: [
                    'reason_code_id' => ReasonCode::adjustment(ReasonCode::COUNT)->id,
                    'stock_count_id' => $stockCount->id,
                    'note' => $note,
                ],
            );
        }

        Stock::query()->whereKey($stock->id)->update(['last_counted_at' => now()]);

        return $transaction;
    }

    /**
     * Stock consumed by a machine, at the site where the machine currently is (SPEC 5.2, 5.8).
     */
    public function issueToMachine(Item $item, Machine $machine, string $qty, User $user, ?string $note = null): StockTransaction
    {
        return DB::transaction(function () use ($item, $machine, $qty, $user, $note) {
            // Re-read under lock: the machine may have been relocated or deactivated since the form was opened.
            $machine = Machine::query()->with('site')->whereKey($machine->id)->sharedLock()->firstOrFail();

            if (! $machine->is_active) {
                throw new StockException(__('Machine :sku is inactive; stock cannot be issued to it.', ['sku' => $machine->sku]));
            }

            return $this->post(
                type: TransactionType::IssueMachine,
                item: $item,
                site: $machine->site,
                qtyDelta: Decimal::negate($this->positive($qty)),
                user: $user,
                incomingCost: null,
                references: ['machine_id' => $machine->id, 'note' => $note],
            );
        }, self::ATTEMPTS);
    }

    /**
     * Stock consumed outside any machine: workshop, building, samples (SPEC 1a, 5.2).
     */
    public function issueGeneral(Item $item, Site $site, string $qty, ReasonCode $reason, User $user, ?string $note = null): StockTransaction
    {
        if ($reason->applies_to !== ReasonCodeScope::IssueGeneral) {
            throw new InvalidArgumentException('A general issue needs a general-issue reason code.');
        }

        return DB::transaction(fn () => $this->post(
            type: TransactionType::IssueGeneral,
            item: $item,
            site: $site,
            qtyDelta: Decimal::negate($this->positive($qty)),
            user: $user,
            incomingCost: null,
            references: ['reason_code_id' => $reason->id, 'note' => $note],
        ), self::ATTEMPTS);
    }

    /**
     * Site-to-site transfer in one step, entered by the receiving site on arrival (SPEC 3.7, 5.2).
     *
     * TRANSFER_OUT at the sender is valued at the sender's average; TRANSFER_IN at the receiver
     * enters at that same unit cost and feeds the receiver's average.
     *
     * @return array{out: StockTransaction, in: StockTransaction}
     */
    public function transfer(Item $item, Site $from, Site $to, string $qty, User $user, ?string $note = null): array
    {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('A transfer needs two different sites.');
        }

        $qty = $this->positive($qty);

        return DB::transaction(function () use ($item, $from, $to, $qty, $user, $note) {
            // Both rows locked in site id order, so opposite transfers cannot deadlock each other.
            foreach (collect([$from, $to])->sortBy('id') as $site) {
                $this->lockedStock($item, $site);
            }

            $group = (string) Str::uuid();

            try {
                $out = $this->post(
                    type: TransactionType::TransferOut,
                    item: $item,
                    site: $from,
                    qtyDelta: Decimal::negate($qty),
                    user: $user,
                    incomingCost: null,
                    references: ['counter_site_id' => $to->id, 'transfer_group' => $group, 'note' => $note],
                );
            } catch (StockException $e) {
                throw StockException::insufficientForTransfer($e->getMessage(), $from->code);
            }

            $in = $this->post(
                type: TransactionType::TransferIn,
                item: $item,
                site: $to,
                qtyDelta: $qty,
                user: $user,
                incomingCost: $out->unit_cost,
                references: ['counter_site_id' => $from->id, 'transfer_group' => $group, 'note' => $note],
            );

            return ['out' => $out, 'in' => $in];
        }, self::ATTEMPTS);
    }

    /**
     * Goods received against a purchase order line, at the order's site and the line's net price
     * (SPEC 5.2). The caller locks the order and line and updates qty_received in the same
     * database transaction; see PurchaseOrderService::receive().
     */
    public function receipt(PurchaseOrderLine $line, Site $site, string $qty, User $user): StockTransaction
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('A receipt must run inside the transaction that updates its order line.');
        }

        return $this->post(
            type: TransactionType::Receipt,
            item: $line->item,
            site: $site,
            qtyDelta: $this->positive($qty),
            user: $user,
            incomingCost: $line->unit_price,
            references: ['purchase_order_line_id' => $line->id],
        );
    }

    /**
     * Rebuild quantity and average cost for one item at one site from the ledger (SPEC 13.8).
     *
     * @return array{qty: string, avg_cost: string}
     */
    public function replay(Item $item, Site $site): array
    {
        $qty = '0';
        $avg = '0';

        $rows = StockTransaction::query()
            ->where('item_id', $item->id)
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get(['qty_delta', 'unit_cost']);

        foreach ($rows as $row) {
            [$qty, $avg] = $this->apply($qty, $avg, $row->qty_delta, $row->unit_cost);
        }

        return ['qty' => Decimal::round($qty, 3), 'avg_cost' => Decimal::round($avg, 4)];
    }

    /**
     * Shared by every movement type. Callers run it inside DB::transaction.
     *
     * @param  string  $qtyDelta  signed: positive in, negative out
     * @param  string|null  $incomingCost  cost of an incoming movement; null means "at the current average"
     * @param  array<string, mixed>  $references  machine_id, counter_site_id, reason_code_id, …
     */
    protected function post(
        TransactionType $type,
        Item $item,
        Site $site,
        string $qtyDelta,
        User $user,
        ?string $incomingCost,
        array $references = [],
    ): StockTransaction {
        $stock = $this->lockedStock($item, $site);
        $incoming = Decimal::compare($qtyDelta, '0') > 0;

        if ($incoming) {
            $unitCost = $incomingCost ?? $stock->avg_cost;

            // No cost entered, nothing in stock and no average yet: the value is unknown (SPEC 5.1).
            if ($incomingCost === null && Decimal::isZero($stock->qty) && Decimal::isZero($stock->avg_cost)) {
                throw StockException::costRequired($site->code);
            }
        } else {
            if (Decimal::compare(Decimal::add($stock->qty, $qtyDelta), '0') < 0) {
                throw StockException::insufficient($stock->qty, $item->uom, $site->code);
            }

            $unitCost = $stock->avg_cost;
        }

        $unitCost = Decimal::round($unitCost, 4);
        [$qtyAfter, $avgAfter] = $this->apply($stock->qty, $stock->avg_cost, $qtyDelta, $unitCost);

        $transaction = StockTransaction::query()->create([
            'type' => $type,
            'item_id' => $item->id,
            'site_id' => $site->id,
            'qty_delta' => Decimal::round($qtyDelta, 3),
            'unit_cost' => $unitCost,
            'value' => Decimal::round(Decimal::mul($qtyDelta, $unitCost), 4),
            'qty_after' => $qtyAfter,
            'avg_cost_after' => $avgAfter,
            'user_id' => $user->id,
            ...$references,
        ]);

        $stock->forceFill(['qty' => $qtyAfter, 'avg_cost' => $avgAfter])->save();

        return $transaction;
    }

    /**
     * One step of the moving average (SPEC 5.1).
     *
     * @return array{0: string, 1: string} quantity after, average cost after
     */
    private function apply(string $qty, string $avg, string $qtyDelta, string $unitCost): array
    {
        $qtyAfter = Decimal::round(Decimal::add($qty, $qtyDelta), 3);

        if (Decimal::compare($qtyDelta, '0') <= 0) {
            return [$qtyAfter, Decimal::round($avg, 4)];
        }

        if (Decimal::compare($qty, '0') <= 0) {
            return [$qtyAfter, Decimal::round($unitCost, 4)];
        }

        $total = Decimal::add(Decimal::mul($qty, $avg), Decimal::mul($qtyDelta, $unitCost));

        return [$qtyAfter, Decimal::round(Decimal::div($total, $qtyAfter), 4)];
    }

    /**
     * The stocks row, created on first use and locked for the rest of the transaction (SPEC 5.2.4).
     */
    private function lockedStock(Item $item, Site $site): Stock
    {
        $lock = fn () => Stock::query()
            ->where('item_id', $item->id)
            ->where('site_id', $site->id)
            ->lockForUpdate()
            ->first();

        // Lock first: INSERT IGNORE on an existing row takes a shared lock, and two
        // transactions upgrading shared locks to exclusive ones deadlock each other.
        if ($stock = $lock()) {
            return $stock;
        }

        // First movement for this item here. INSERT IGNORE: concurrent first movements
        // cannot both create the row; the loser simply locks the winner's row.
        Stock::query()->insertOrIgnore([
            'item_id' => $item->id,
            'site_id' => $site->id,
            'bin' => $item->category()->value('default_bin'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $lock() ?? throw new \RuntimeException("Stock row for item {$item->id} at site {$site->id} could not be created.");
    }

    private function positive(string $qty): string
    {
        if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $qty) || Decimal::isZero($qty)) {
            throw new InvalidArgumentException("Quantity must be a positive number with at most 3 decimals, got [{$qty}].");
        }

        return $qty;
    }
}
